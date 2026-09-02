<?php
function config_env_value($name, $default = null) {
    $value = getenv((string) $name);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

function config_env_bool($name, $default = false) {
    $value = getenv((string) $name);
    if ($value === false || $value === null || $value === '') {
        return (bool) $default;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function load_dotenv_file($path) {
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $name = trim(substr($trimmed, 0, $separatorPos));
        $value = trim(substr($trimmed, $separatorPos + 1));
        if ($name === '') {
            continue;
        }

        if (
            (strlen($value) >= 2) &&
            (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function define_config_const($name, $value) {
    if (!defined((string) $name)) {
        define((string) $name, $value);
    }
}

function resolve_app_environment() {
    $env = strtolower(trim((string) config_env_value('APP_ENV', '')));
    if ($env !== '') {
        return $env;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '' || $host === 'localhost' || strpos($host, '127.0.0.1') !== false || strpos($host, '.local') !== false) {
        return 'local';
    }

    return 'production';
}

function load_instance_config() {
    $instanceFile = __DIR__ . '/instance.php';
    if (!file_exists($instanceFile)) {
        return [];
    }

    $instanceConfig = require $instanceFile;
    return is_array($instanceConfig) ? $instanceConfig : [];
}

function load_instance_secrets() {
    $secretFile = __DIR__ . '/instance.secrets.php';
    if (file_exists($secretFile)) {
        $secrets = require $secretFile;
        return is_array($secrets) ? $secrets : [];
    }

    $randomSecret = bin2hex(random_bytes(32));
    $payload = "<?php\nreturn [\n    'app_encryption_key' => '" . $randomSecret . "',\n];\n";
    @file_put_contents($secretFile, $payload, LOCK_EX);

    if (file_exists($secretFile)) {
        @chmod($secretFile, 0600);
        $secrets = require $secretFile;
        return is_array($secrets) ? $secrets : [];
    }

    return [];
}

load_dotenv_file(__DIR__ . '/../.env');

$appEnv = resolve_app_environment();
$isLocal = $appEnv === 'local' || $appEnv === 'development';
$isHttpsRequest =
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
$instanceConfig = load_instance_config();
$instanceSecrets = load_instance_secrets();

$config = [
    'app_env' => $appEnv,
    'app_debug' => $isLocal,
    'db_host' => $isLocal ? '127.0.0.1' : 'localhost',
    'db_name' => $isLocal ? 'jaxe_local' : 'jaxe_production',
    'db_user' => $isLocal ? 'root' : '',
    'db_pass' => '',
    'auto_sync_schema' => $appEnv !== 'production',
    'install_sql_path' => __DIR__ . '/../install.sql',
    'migrations_path' => __DIR__ . '/../database/migrations',
    'public_path' => __DIR__ . '/../public',
    'publication_api_webhook' => '',
    'kpi_collection_api_webhook' => '',
    'mail_from_email' => '',
    'mail_from_name' => 'Jaxe Ops',
    'validation_notification_emails' => '',
    'session_cookie_secure' => !$isLocal && $isHttpsRequest,
    'session_cookie_samesite' => 'Lax',
];

if (isset($instanceSecrets['app_encryption_key']) && trim((string) $instanceSecrets['app_encryption_key']) !== '') {
    $config['app_encryption_key'] = (string) $instanceSecrets['app_encryption_key'];
}

if (!empty($instanceConfig)) {
    $config = array_merge($config, $instanceConfig);
}

$config['app_env'] = (string) config_env_value('APP_ENV', (string) ($config['app_env'] ?? $appEnv));
$config['app_debug'] = config_env_bool('APP_DEBUG', (bool) ($config['app_debug'] ?? $isLocal));
$config['auto_sync_schema'] = config_env_bool('AUTO_SYNC_SCHEMA', (bool) ($config['auto_sync_schema'] ?? false));
$config['uploads_path'] = (string) config_env_value('UPLOADS_PATH', (string) ($config['uploads_path'] ?? (__DIR__ . '/../public/uploads')));
$envDbSuffix = strtolower((string) $config['app_env']) === 'production' ? 'PROD' : 'LOCAL';
$dbHostKey = 'DB_HOST_' . $envDbSuffix;
$dbNameKey = 'DB_DATABASE_' . $envDbSuffix;
$dbUserKey = 'DB_USERNAME_' . $envDbSuffix;
$dbPassKey = 'DB_PASSWORD_' . $envDbSuffix;

$config['db_host'] = (string) config_env_value(
    $dbHostKey,
    (string) config_env_value('DB_HOST', (string) ($config['db_host'] ?? 'localhost'))
);
$config['db_name'] = (string) config_env_value(
    $dbNameKey,
    (string) config_env_value(
        'DB_DATABASE',
        (string) config_env_value('DB_NAME', (string) ($config['db_name'] ?? 'jaxe_ops'))
    )
);
$config['db_user'] = (string) config_env_value(
    $dbUserKey,
    (string) config_env_value(
        'DB_USERNAME',
        (string) config_env_value('DB_USER', (string) ($config['db_user'] ?? 'root'))
    )
);
$config['db_pass'] = (string) config_env_value(
    $dbPassKey,
    (string) config_env_value(
        'DB_PASSWORD',
        (string) config_env_value('DB_PASS', (string) ($config['db_pass'] ?? ''))
    )
);
$config['session_cookie_secure'] = config_env_bool('SESSION_COOKIE_SECURE', (bool) ($config['session_cookie_secure'] ?? !$isLocal));
$config['session_cookie_samesite'] = (string) config_env_value('SESSION_COOKIE_SAMESITE', (string) ($config['session_cookie_samesite'] ?? 'Lax'));
$config['mail_from_email'] = (string) config_env_value('MAIL_FROM_EMAIL', (string) ($config['mail_from_email'] ?? ''));
$config['mail_from_name'] = (string) config_env_value('MAIL_FROM_NAME', (string) ($config['mail_from_name'] ?? 'Strax'));
$config['validation_notification_emails'] = (string) config_env_value('VALIDATION_NOTIFICATION_EMAILS', (string) ($config['validation_notification_emails'] ?? ''));
$config['smtp_host'] = (string) config_env_value('SMTP_HOST', '');
$config['smtp_port'] = (int) config_env_value('SMTP_PORT', '587');
$config['smtp_secure'] = strtolower((string) config_env_value('SMTP_SECURE', 'tls'));
$config['smtp_username'] = (string) config_env_value('SMTP_USERNAME', '');
$config['smtp_password'] = (string) config_env_value('SMTP_PASSWORD', '');
$config['smtp_timeout'] = (int) config_env_value('SMTP_TIMEOUT', '15');
$config['publication_api_webhook'] = (string) config_env_value('PUBLICATION_API_WEBHOOK', (string) ($config['publication_api_webhook'] ?? ''));
$config['kpi_collection_api_webhook'] = (string) config_env_value('KPI_COLLECTION_API_WEBHOOK', (string) ($config['kpi_collection_api_webhook'] ?? ''));
$config['app_encryption_key'] = (string) config_env_value('APP_ENCRYPTION_KEY', (string) ($config['app_encryption_key'] ?? ''));

if (trim((string) ($config['app_encryption_key'] ?? '')) === '') {
    $config['app_encryption_key'] = 'change-me-in-production';
}

define_config_const('APP_ENV', (string) ($config['app_env'] ?? 'production'));
define_config_const('APP_DEBUG', (bool) ($config['app_debug'] ?? false));
define_config_const('DB_HOST', (string) ($config['db_host'] ?? 'localhost'));
define_config_const('DB_NAME', (string) ($config['db_name'] ?? 'jaxe_ops'));
define_config_const('DB_USER', (string) ($config['db_user'] ?? 'root'));
define_config_const('DB_PASS', (string) ($config['db_pass'] ?? ''));
define_config_const('AUTO_SYNC_SCHEMA', (bool) ($config['auto_sync_schema'] ?? true));
define_config_const('INSTALL_SQL_PATH', (string) ($config['install_sql_path'] ?? (__DIR__ . '/../install.sql')));
define_config_const('MIGRATIONS_PATH', (string) ($config['migrations_path'] ?? (__DIR__ . '/../database/migrations')));
define_config_const('PUBLIC_PATH', (string) ($config['public_path'] ?? (__DIR__ . '/../public')));
define_config_const('UPLOADS_PATH', (string) ($config['uploads_path'] ?? (PUBLIC_PATH . '/uploads')));
define_config_const('PUBLICATION_API_WEBHOOK', (string) ($config['publication_api_webhook'] ?? ''));
define_config_const('KPI_COLLECTION_API_WEBHOOK', (string) ($config['kpi_collection_api_webhook'] ?? ''));
define_config_const('MAIL_FROM_EMAIL', (string) ($config['mail_from_email'] ?? ''));
define_config_const('MAIL_FROM_NAME', (string) ($config['mail_from_name'] ?? 'Jaxe Ops'));
define_config_const('SMTP_HOST', (string) ($config['smtp_host'] ?? ''));
define_config_const('SMTP_PORT', (int) ($config['smtp_port'] ?? 587));
define_config_const('SMTP_SECURE', (string) ($config['smtp_secure'] ?? 'tls'));
define_config_const('SMTP_USERNAME', (string) ($config['smtp_username'] ?? ''));
define_config_const('SMTP_PASSWORD', (string) ($config['smtp_password'] ?? ''));
define_config_const('SMTP_TIMEOUT', (int) ($config['smtp_timeout'] ?? 15));
define_config_const('VALIDATION_NOTIFICATION_EMAILS', (string) ($config['validation_notification_emails'] ?? ''));
define_config_const('APP_ENCRYPTION_KEY', (string) ($config['app_encryption_key'] ?? ''));
define_config_const('SESSION_COOKIE_SECURE', (bool) ($config['session_cookie_secure'] ?? false));
define_config_const('SESSION_COOKIE_SAMESITE', (string) ($config['session_cookie_samesite'] ?? 'Lax'));

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => SESSION_COOKIE_SAMESITE,
    ]);
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$publicBaseUrl = rtrim(dirname($scriptName), '/');
$appBaseUrl = preg_replace('#/public$#', '', $publicBaseUrl);

define('PUBLIC_BASE_URL', $publicBaseUrl === '/' ? '' : $publicBaseUrl);
define('BASE_URL', $appBaseUrl === '/' ? '' : $appBaseUrl);

function app_url($path = '') {
    $path = '/' . ltrim($path, '/');
    return (BASE_URL === '' ? '' : BASE_URL) . ($path === '/' ? '' : $path);
}

function route_url($path = '') {
    $path = '/' . ltrim($path, '/');
    $frontController = (BASE_URL === '' ? '' : BASE_URL) . '/index.php';
    return $frontController . ($path === '/' ? '' : $path);
}

function upload_url($relativePath = '') {
    $relativePath = ltrim((string) $relativePath, '/');
    return app_url('/public/uploads/' . $relativePath);
}

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// TCPDF est embarqué avec l'application : les exports restent disponibles
// même lorsque l'hébergement cPanel n'exécute pas Composer au déploiement.
$bundledTcpdf = __DIR__ . '/../app/third_party/tcpdf/tcpdf.php';
if (!class_exists('TCPDF', false) && file_exists($bundledTcpdf)) {
    require_once $bundledTcpdf;
}

// Autoloader projet
spl_autoload_register(function ($class) {
    $directories = [
        __DIR__ . '/../app/core/',
        __DIR__ . '/../app/controllers/',
        __DIR__ . '/../app/models/',
        __DIR__ . '/../app/helpers/'
    ];

    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
