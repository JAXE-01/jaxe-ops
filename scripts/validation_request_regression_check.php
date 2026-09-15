<?php
// Isolated regression test: in-memory database and fake mail transport, no real sends.
if (PHP_SAPI !== 'cli') exit;
class Database {
    public static PDO $db;
    public static function getConnection() { return self::$db; }
}
class TenantGuard { public static function tenantId() { return 1; } }
class AgencyAccessPolicy {
    public static function assertRecordCapability($table, $row, $capability, $write = false) {
        if (!$row || (int) $row['tenant_id'] !== 1) throw new RuntimeException('Access denied');
    }
}
class StraxMailTransport {
    public static array $messages = [];
    public static bool $success = true;
    public static function send($to, $subject, $body, $url, $label) {
        self::$messages[] = compact('to', 'subject', 'body', 'url');
        return self::$success;
    }
}
function route_url($path) { return 'https://example.test' . $path; }
require dirname(__DIR__) . '/app/helpers/ModuleRegistry.php';
require dirname(__DIR__) . '/app/helpers/UserRoles.php';
require dirname(__DIR__) . '/app/helpers/UserScope.php';
require dirname(__DIR__) . '/app/helpers/ValidationRequestService.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$db = Database::$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE clients(id INTEGER,tenant_id INTEGER,organization_id INTEGER);
CREATE TABLE projets(id INTEGER,client_id INTEGER);
CREATE TABLE users(id INTEGER,nom TEXT,email TEXT,statut TEXT);
CREATE TABLE livrable_items(id INTEGER,titre TEXT,numero_ordre INTEGER,statut TEXT);
CREATE TABLE taches_pipeline(id INTEGER,titre TEXT,auteur_id INTEGER,parent_task_id INTEGER,livrable_item_id INTEGER,plan_mensuel_id INTEGER,projet_id INTEGER,type_tache TEXT,statut TEXT,validation_decision TEXT);
CREATE TABLE workflow_progress_reports(tenant_id INTEGER,task_id INTEGER,sender_id INTEGER,subject TEXT,message TEXT,primary_recipient TEXT,cc_recipients TEXT,delivery_status TEXT);
INSERT INTO clients VALUES(1,1,1),(2,2,2);
INSERT INTO projets VALUES(1,1),(2,2);
INSERT INTO users VALUES(10,"Internal","internal@example.test","Actif"),(11,"Client manager","client@example.test","Actif"),(12,"Inactive","inactive@example.test","Inactif");');
$taskInsert = $db->prepare('INSERT INTO taches_pipeline VALUES(?,?,?,?,?,?,?,?,?,?)');
for ($i = 1; $i <= 8; $i++) {
    $db->prepare('INSERT INTO livrable_items VALUES(?,?,?,"En cours")')->execute([$i, 'Content ' . $i, $i]);
    $taskInsert->execute([$i * 10, 'Production', 20, null, $i, 1, 1, 'Production', 'Terminee', null]);
    $taskInsert->execute([$i * 10 + 1, 'Internal review', 10, $i * 10, $i, 1, 1, 'Validation interne', 'A faire', null]);
    $taskInsert->execute([$i * 10 + 2, 'Client review', 11, $i * 10 + 1, $i, 1, 1, 'Validation client', 'Bloquee', null]);
}
$db->exec("UPDATE taches_pipeline SET statut='Terminee',validation_decision='Valide' WHERE id=31;
UPDATE taches_pipeline SET statut='A faire' WHERE id=32;
UPDATE taches_pipeline SET statut='En cours' WHERE id=40;
UPDATE taches_pipeline SET plan_mensuel_id=2 WHERE livrable_item_id=5;
UPDATE taches_pipeline SET auteur_id=NULL WHERE id=61;
UPDATE taches_pipeline SET auteur_id=12 WHERE id=71;
UPDATE taches_pipeline SET auteur_id=21 WHERE id=80;");
$user = ['id' => 20, 'nom' => 'Designer', 'role' => 'Admin'];
$context = ['type_tache' => 'Production', 'plan_mensuel_id' => 1, 'projet_id' => 1, 'periode_mois' => '2026-09-01'];
$service = new ValidationRequestService();
$before = $db->query('SELECT * FROM taches_pipeline ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$result = $service->send($context, $user);
check($result === ['sent' => 1, 'failed' => 0, 'unassigned' => 2, 'tasks' => 5], 'Grouping, eligibility or missing recipients');
check(count(StraxMailTransport::$messages) === 1, 'Expected one digest per responsible');
$mail = StraxMailTransport::$messages[0];
check($mail['to'] === ['internal@example.test'], 'Wrong internal responsible');
foreach ([11, 21, 81] as $id) check(str_contains($mail['body'], '/task/' . $id), 'Missing validation URL');
check(!str_contains($mail['body'], '/task/31') && !str_contains($mail['body'], '/task/41'), 'Approved or unfinished content included');
check($db->query('SELECT COUNT(*) FROM workflow_progress_reports')->fetchColumn() == 3, 'History missing');
check($before === $db->query('SELECT * FROM taches_pipeline ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Reminder changed task decisions');
$user['role'] = 'Designer';
check(count($service->pending($context, $user)) === 4, 'Operational author scope ignored');
$user['role'] = 'Admin';
$db->exec('UPDATE taches_pipeline SET auteur_id=11 WHERE id=81');
check($service->send($context, $user)['sent'] === 2, 'Multiple responsible users not notified separately');
$db->exec("UPDATE taches_pipeline SET statut='Annulee' WHERE id=21; UPDATE livrable_items SET statut='Exclu' WHERE id=8");
check(count($service->pending($context, $user)) === 3, 'Cancelled or excluded content included');
$user['role'] = 'Clientele';
$denied = false;
try { $service->pending($context, $user); } catch (RuntimeException $e) { $denied = true; }
check($denied, 'Unauthorized stage accepted');
$user['role'] = 'Admin';
$context['type_tache'] = 'Validation interne';
$result = $service->send($context, $user);
check($result['tasks'] === 1 && end(StraxMailTransport::$messages)['to'] === ['client@example.test'], 'Client handoff uses wrong responsible or includes unapproved content');
StraxMailTransport::$success = false;
$result = $service->send($context, $user);
check($result['sent'] === 0 && $result['failed'] === 1, 'Mail failure misreported');
check($db->query("SELECT COUNT(*) FROM workflow_progress_reports WHERE delivery_status='Failed'")->fetchColumn() == 1, 'Failure history missing');
$context['projet_id'] = 2;
$denied = false;
try { $service->pending($context, $user); } catch (RuntimeException $e) { $denied = true; }
check($denied, 'Cross-tenant access accepted');
$context['projet_id'] = 1;
$context['plan_mensuel_id'] = 99;
check($service->send($context, $user)['tasks'] === 0, 'Empty calendar is not empty');
echo "OK: eligibility, month, author and tenant scope, distinct validators, grouped mail, failures, history, unchanged decisions. No real mail sent.\n";
