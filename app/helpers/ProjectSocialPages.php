<?php
/** Explicit client/project assignments. Call save inside the project transaction. */
class ProjectSocialPages {
    public static function available(): array {
        $stmt = Database::getConnection()->prepare("SELECT id,client_id,provider,account_label FROM social_connections WHERE tenant_id=:tenant AND status='Connected' ORDER BY account_label");
        $stmt->execute(['tenant'=>TenantGuard::tenantId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function selected(int $project): array {
        $stmt=Database::getConnection()->prepare('SELECT connection_id FROM social_connection_projects WHERE project_id=:project AND tenant_id=:tenant');
        $stmt->execute(['project'=>$project,'tenant'=>TenantGuard::tenantId()]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    public static function save(int $project, int $client, array $ids): void {
        TenantGuard::assertClient($client);
        $db=Database::getConnection();
        $check=$db->prepare('SELECT p.id FROM projets p JOIN clients c ON c.id=p.client_id WHERE p.id=:project AND p.client_id=:client AND c.tenant_id=:tenant');
        $check->execute(['project'=>$project,'client'=>$client,'tenant'=>TenantGuard::tenantId()]);
        if(!$check->fetchColumn()) throw new RuntimeException('Projet inaccessible pour ce client.');
        $ids=array_values(array_unique(array_map('intval',$ids)));
        $check=$db->prepare("SELECT id FROM social_connections WHERE id=:id AND client_id=:client AND tenant_id=:tenant AND status='Connected'");
        foreach($ids as $id){
            $check->execute(['id'=>$id,'client'=>$client,'tenant'=>TenantGuard::tenantId()]);
            if(!$check->fetchColumn()) throw new RuntimeException('Une page sélectionnée ne fait pas partie des comptes connectés de ce client.');
        }
        $db->prepare('DELETE FROM social_connection_projects WHERE project_id=:project AND tenant_id=:tenant')->execute(['project'=>$project,'tenant'=>TenantGuard::tenantId()]);
        $insert=$db->prepare('INSERT INTO social_connection_projects(tenant_id,connection_id,project_id,created_by) VALUES(:tenant,:connection,:project,:user)');
        foreach($ids as $id) $insert->execute(['tenant'=>TenantGuard::tenantId(),'connection'=>$id,'project'=>$project,'user'=>(int)($_SESSION['user']['id']??0)]);
    }
}
