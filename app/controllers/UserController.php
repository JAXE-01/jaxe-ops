<?php
class UserController extends CrudController {
    protected $moduleKey = 'user';
    public function __construct(){parent::__construct();$this->model=new ManagedUserModel();}

    protected function collectRequestData(array $currentRecord = []) {
        $payload = parent::collectRequestData($currentRecord);
        $payload['secondary_roles'] = UserRoles::serialize($payload['secondary_roles'] ?? []);

        if (!empty($payload['role'])) {
            $secondaryRoles = UserRoles::normalizeList($payload['secondary_roles']);
            $secondaryRoles = array_values(array_filter($secondaryRoles, function ($role) use ($payload) {
                return $role !== (string) $payload['role'];
            }));
            $payload['secondary_roles'] = UserRoles::serialize($secondaryRoles);
        }

        if (array_key_exists('password', $payload) && trim((string)$payload['password']) === '' && !empty($currentRecord)) {
            unset($payload['password']);
        }

        return $payload;
    }

    public function delete($id) {
        $this->requireModulePermission('manage');
        $id = (int) $id;
        if ($id === (int) ($this->currentUser()['id'] ?? 0)) {
            $this->flash('error', 'Vous ne pouvez pas retirer votre propre acces.');
            $this->redirect('/user');
        }
        $stmt = Database::getConnection()->prepare("UPDATE tenant_memberships SET status='Suspendu' WHERE tenant_id=:tenant AND user_id=:user AND status='Actif'");
        $stmt->execute(['tenant' => TenantGuard::tenantId(), 'user' => $id]);
        $this->flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Acces retire de cette entreprise.' : 'Utilisateur introuvable dans cette entreprise.');
        $this->redirect('/user');
    }

    public function bulk() {
        if ((string) ($_POST['bulk_action'] ?? '') !== 'delete') { parent::bulk(); return; }
        $this->requireModulePermission('manage');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['selected_ids'] ?? [])))));
        $current = (int) ($this->currentUser()['id'] ?? 0);
        $ids = array_values(array_filter($ids, static fn($id) => $id !== $current));
        if (!$ids) { $this->flash('error', 'Selectionnez au moins un autre utilisateur.'); $this->redirect('/user'); }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare("UPDATE tenant_memberships SET status='Suspendu' WHERE tenant_id=? AND user_id IN ($marks) AND status='Actif'");
        $stmt->execute(array_merge([TenantGuard::tenantId()], $ids));
        $this->flash('success', $stmt->rowCount() . ' acces retire(s) de cette entreprise.');
        $this->redirect('/user');
    }}
