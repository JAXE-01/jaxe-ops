<?php
class OrganizationController extends Controller {
    public function __construct() {
        parent::__construct();
        $this->requirePermission('settings.manage');
        if(!OrganizationContext::canManageOrganizations($this->currentUser())){$this->flash('error','Cet espace est reserve aux agences et a l administration generale.');$this->redirect('/account');}
    }

    public function index() {
        $tenant = TenantContext::resolveForUser($this->currentUser());
        if (!$tenant) {
            throw new RuntimeException('Aucune entreprise active.');
        }
        if ($this->isPost()) {
            $this->handleAction((int) $tenant['id']);
        }
        $pdo = Database::getConnection();
        $orgStmt = $pdo->prepare('SELECT * FROM organizations WHERE tenant_id = :tenant_id ORDER BY name');
        $orgStmt->execute(['tenant_id' => (int) $tenant['id']]);
        $memberStmt = $pdo->prepare("SELECT tm.*, u.nom, u.email, u.role AS legacy_role, o.name AS organization_name
            FROM tenant_memberships tm JOIN users u ON u.id = tm.user_id
            LEFT JOIN organizations o ON o.id = tm.organization_id
            WHERE tm.tenant_id = :tenant_id ORDER BY u.nom, u.email");
        $memberStmt->execute(['tenant_id' => (int) $tenant['id']]);
        $this->render('organizations/index', [
            'pageTitle' => 'Entreprises et utilisateurs',
            'tenant' => $tenant,
            'organizations' => $orgStmt->fetchAll(PDO::FETCH_ASSOC),
            'memberships' => $memberStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    private function handleAction($tenantId) {
        $action = trim((string) ($_POST['action'] ?? ''));
        $pdo = Database::getConnection();
        try {
            if ($action === 'create_organization') {
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Nom de l organisation obligatoire.');
                $accountType = (string) ($_POST['account_type'] ?? 'ClientCompany');
                $projectMode = (string) ($_POST['project_mode'] ?? ($accountType === 'Agency' ? 'Multiple' : 'Single'));
                if (!in_array($accountType, ['Agency','ClientCompany'], true)) throw new RuntimeException('Type de compte invalide.');
                if (!in_array($projectMode, ['Single','Multiple'], true)) throw new RuntimeException('Mode projet invalide.');
                $registrationState = (string) ($_POST['registration_state'] ?? ($accountType === 'Agency' ? 'Registered' : 'ExternalProfile'));
                if (!in_array($registrationState, ['Registered','ExternalProfile'], true)) throw new RuntimeException('Etat d inscription invalide.');
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)), '-');
                $stmt = $pdo->prepare("INSERT INTO organizations (tenant_id, name, slug, account_type, project_mode, registration_state, status) VALUES (:tenant_id,:name,:slug,:account_type,:project_mode,:registration_state,'Actif')");
                $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'slug' => $slug . '-' . bin2hex(random_bytes(2)), 'account_type' => $accountType, 'project_mode' => $projectMode, 'registration_state' => $registrationState]);
                $this->flash('success', 'Organisation creee.');
            } elseif ($action === 'invite') {
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $name = trim((string) ($_POST['user_name'] ?? ''));
                $organizationId = (int) ($_POST['organization_id'] ?? 0);
                TenantGuard::assertOrganization($organizationId);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email invalide.');
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                $userId = (int) ($stmt->fetchColumn() ?: 0);
                $temporaryPassword = null;
                if ($userId <= 0) {
                    $temporaryPassword = 'Jx!' . bin2hex(random_bytes(6));
                    $stmt = $pdo->prepare("INSERT INTO users (nom,email,password,role,statut) VALUES (:nom,:email,:password,'Clientele','Actif')");
                    $stmt->execute(['nom' => $name !== '' ? $name : $email, 'email' => $email, 'password' => password_hash($temporaryPassword, PASSWORD_BCRYPT)]);
                    $userId = (int) $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO tenant_memberships (tenant_id,organization_id,user_id,membership_role,status,invited_at)
                    VALUES (:tenant_id,:organization_id,:user_id,'Client','Invite',NOW())
                    ON DUPLICATE KEY UPDATE organization_id=VALUES(organization_id), membership_role='Client', status='Invite', invited_at=NOW()");
                $stmt->execute(['tenant_id' => $tenantId, 'organization_id' => $organizationId, 'user_id' => $userId]);
                $message = 'Invitation enregistree.' . ($temporaryPassword ? ' Mot de passe temporaire : ' . $temporaryPassword : '');
                $this->flash('success', $message);
            } elseif ($action === 'update_membership') {
                $membershipId = (int) ($_POST['membership_id'] ?? 0);
                $role = (string) ($_POST['membership_role'] ?? 'Member');
                $status = (string) ($_POST['membership_status'] ?? 'Actif');
                if (!in_array($role, ['Owner','Admin','Manager','Member','Client'], true)) throw new RuntimeException('Role invalide.');
                if (!in_array($status, ['Invite','Actif','Suspendu'], true)) throw new RuntimeException('Statut invalide.');
                $stmt = $pdo->prepare('UPDATE tenant_memberships SET membership_role=:role,status=:status,joined_at=IF(:status_joined="Actif",COALESCE(joined_at,NOW()),joined_at) WHERE id=:id AND tenant_id=:tenant_id');
                $stmt->execute(['role' => $role, 'status' => $status, 'status_joined' => $status, 'id' => $membershipId, 'tenant_id' => $tenantId]);
                $this->flash('success', 'Membership mis a jour.');
            }
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/organization');
    }
}
