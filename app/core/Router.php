<?php
class Router {
    public function dispatch($uri) {
        $uri = parse_url($uri, PHP_URL_PATH);
        $prefixes = array_filter([PUBLIC_BASE_URL, BASE_URL], static function ($value) { return $value !== ''; });
        usort($prefixes, static function ($left, $right) { return strlen($right) <=> strlen($left); });
        foreach ($prefixes as $prefix) {
            if (strpos($uri, $prefix) === 0) { $uri = substr($uri, strlen($prefix)); break; }
        }
        $uri = trim($uri, '/');
        if ($uri === 'index.php') { $uri = ''; }
        elseif (strpos($uri, 'index.php/') === 0) { $uri = substr($uri, strlen('index.php/')); }

        $segments = $uri === '' ? [] : explode('/', $uri);
        $controllerName = !empty($segments[0]) ? $this->resolveControllerName($segments[0]) : 'HomeController';
        $method = isset($segments[1]) ? $this->resolveMethodName($segments[1]) : 'index';
        $params = array_slice($segments, 2);
        if (!empty($segments[0]) && in_array($segments[0], ['login', 'logout'], true)) {
            $controllerName = 'AuthController';
            $method = $segments[0];
            $params = [];
        }

        if ($this->isDestructiveMethod($method) && (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')) {
            http_response_code(405);
            header('Allow: POST');
            echo 'Methode non autorisee.';
            return;
        }

        if (class_exists($controllerName)) {
            $controller = new $controllerName();
            if (method_exists($controller, $method)) {
                call_user_func_array([$controller, $method], $params);
                return;
            }
        }
        http_response_code(404);
        echo 'Page non trouvée';
    }

    private function isDestructiveMethod($method) {
        return in_array((string) $method, ['delete', 'deleteSocialAccount', 'revokePublicValidationLink', 'purge', 'logout'], true);
    }

    private function resolveControllerName($segment) {
        $normalized = str_replace(' ', '', ucwords(str_replace('-', ' ', $segment)));
        $normalized = str_replace('Oauth', 'OAuth', $normalized);
        return $normalized . 'Controller';
    }
    private function resolveMethodName($segment) {
        $normalized = str_replace(' ', '', ucwords(str_replace('-', ' ', (string) $segment)));
        return lcfirst($normalized);
    }}
