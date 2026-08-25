<?php
class Controller {
    private $permissionModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->ensureCsrfToken();
        if ($this->isPost()) {
            $this->verifyCsrfToken();
        }
    }

    public function render($view, $data = []) {
        extract($data);
        ob_start();
        require __DIR__ . '/../views/' . $view . '.php';
        $content = ob_get_clean();
        ob_start();
        require __DIR__ . '/../views/layouts/main.php';
        $page = ob_get_clean();
        echo $this->protectRenderedForms($page);
    }

    protected function isPost() {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function csrfToken() {
        $this->ensureCsrfToken();
        return (string) $_SESSION['_csrf_token'];
    }

    private function ensureCsrfToken() {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    private function verifyCsrfToken() {
        $submitted = (string) ($_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $expected = (string) ($_SESSION['_csrf_token'] ?? '');
        if ($submitted !== '' && $expected !== '' && hash_equals($expected, $submitted)) {
            return;
        }
        if ($this->isAjaxRequest()) {
            $this->respondJson(['ok' => false, 'message' => 'La session de securite a expire. Rechargez la page puis reessayez.'], 403);
        }
        http_response_code(403);
        echo 'La session de securite a expire. Rechargez la page puis reessayez.';
        exit;
    }

    private function protectRenderedForms($html) {
        $token = htmlspecialchars($this->csrfToken(), ENT_QUOTES, 'UTF-8');
        $html = preg_replace_callback('/<form\b([^>]*)>/i', static function ($matches) use ($token) {
            $attributes = (string) ($matches[1] ?? '');
            if (!preg_match('/\bmethod\s*=\s*(["\']?)post\1/i', $attributes)) {
                return $matches[0];
            }
            return $matches[0] . '<input type="hidden" name="_csrf_token" value="' . $token . '">';
        }, (string) $html);

        $script = '<script>(function(){var token=' . json_encode($this->csrfToken()) . ';document.addEventListener("click",function(event){var link=event.target.closest("a[href]");if(!link){return;}var url=new URL(link.href,window.location.href);if(!/(\\/delete\\/|\\/deleteSocialAccount\\/|\\/revokePublicValidationLink\\/|\\/logout(?:$|\\?))/.test(url.pathname)){return;}event.preventDefault();event.stopImmediatePropagation();if(link.getAttribute("onclick")&&link.getAttribute("onclick").indexOf("confirm")!==-1&&!window.confirm("Confirmer cette action ?")){return;}var form=document.createElement("form");form.method="post";form.action=url.href;var input=document.createElement("input");input.type="hidden";input.name="_csrf_token";input.value=token;form.appendChild(input);document.body.appendChild(form);form.submit();},true);})();</script>';
        return str_replace('</body>', $script . '</body>', $html);
    }

    protected function redirect($path) {
        header('Location: ' . route_url($path));
        exit;
    }

    protected function isAjaxRequest() {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return strpos($accept, 'application/json') !== false;
    }

    protected function respondJson(array $payload, $statusCode = 200) {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function flash($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public function getFlash() {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    public function currentUser() { return $_SESSION['user'] ?? null; }

    protected function requireAuth() {
        if ($this->currentUser() === null) { $this->redirect('/login'); }
    }

    public function can($permissionKey) {
        if ($permissionKey === null || $permissionKey === '') { return true; }
        $user=$this->currentUser();
        if(OrganizationContext::isClientCompany($user)){
            $clientDenied=['clients.view','clients.manage','settings.view','settings.manage','subscriptions.view','subscriptions.manage','integrations.view','integrations.manage'];
            if(in_array((string)$permissionKey,$clientDenied,true))return false;
        }
        if(OrganizationContext::isAgency($user) && !OrganizationContext::isPlatformAdmin($user)){
            $agencyDenied=['settings.view','settings.manage','subscriptions.view','subscriptions.manage','integrations.view','integrations.manage'];
            if(in_array((string)$permissionKey,$agencyDenied,true))return false;
        }
        return $this->getPermissionModel()->userHasPermission($user, $permissionKey);
    }
    protected function requirePermission($permissionKey, $message = 'Acces refuse.') {
        $this->requireAuth();
        if ($this->can($permissionKey)) { return; }
        $this->flash('error', $message);
        $this->redirect($this->getDefaultAuthorizedPath());
    }

    protected function getPermissionModel() {
        if (!$this->permissionModel instanceof PermissionModel) { $this->permissionModel = new PermissionModel(); }
        return $this->permissionModel;
    }

    protected function getDefaultAuthorizedPath() {
        if ($this->currentUser() === null) { return '/login'; }
        $candidates = ['/' => 'dashboard.view','/calendrier' => 'calendar.view','/client' => 'clients.view','/projet' => 'projects.view','/contenu' => 'content.view','/settings' => 'settings.view'];
        foreach ($candidates as $path => $permissionKey) {
            if ($this->can($permissionKey)) { return $path; }
        }
        return '/logout';
    }

    protected function resolvePeriodPreset($preset) {
        $today = new DateTime();
        switch ($preset) {
            case 'current_month': return ['from' => $today->format('Y-m-01'), 'to' => $today->format('Y-m-t')];
            case 'prev_month': $prev = (clone $today)->modify('first day of last month'); return ['from' => $prev->format('Y-m-01'), 'to' => $prev->format('Y-m-t')];
            case 'last_3_months': return ['from' => (clone $today)->modify('-3 months')->format('Y-m-01'), 'to' => $today->format('Y-m-t')];
            case 'next_month': $next = (clone $today)->modify('first day of next month'); return ['from' => $next->format('Y-m-01'), 'to' => $next->format('Y-m-t')];
            case 'next_3_months': return ['from' => $today->format('Y-m-01'), 'to' => (clone $today)->modify('+3 months')->format('Y-m-t')];
            case 'next_6_months': return ['from' => $today->format('Y-m-01'), 'to' => (clone $today)->modify('+6 months')->format('Y-m-t')];
            default: return null;
        }
    }
}
