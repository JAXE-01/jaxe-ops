<?php
class AuthController extends Controller {
    private $authModel;

    public function __construct() {
        parent::__construct();
        $this->authModel = new AuthModel();
    }

    public function login() {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $security = new AuthSecurityService($email);
            if ($security->retryAfterSeconds() > 0) {
                $this->flash('error', 'Trop de tentatives. Réessayez dans quelques minutes.');
                $this->redirect('/login');
            }
            $user = $this->authModel->findByEmail($email);

            if ($user && password_verify($password, $user['password'])) {
                $security->record(true);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'nom' => $user['nom'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'secondary_roles' => $user['secondary_roles'] ?? '',
                    'roles' => UserRoles::extractRoles($user)
                ];
                TenantContext::resolveForUser($_SESSION['user']);
                $this->flash('success', 'Connexion reussie.');
                $this->redirect($this->getDefaultAuthorizedPath());
            }

            $security->record(false);
            $this->flash('error', 'Identifiants invalides.');
            $this->redirect('/login');
        }

        $this->render('auth/login', ['pageTitle' => 'Connexion']);
    }

    public function logout() {
        unset($_SESSION['user'], $_SESSION['workspace_mode'], $_SESSION['social_oauth'], $_SESSION['social_oauth_selection']);
        TenantContext::clear();
        session_regenerate_id(true);
        $this->flash('success', 'Vous êtes maintenant déconnecté.');
        $this->redirect('/login');
    }
}
