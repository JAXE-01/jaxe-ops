<?php
return [
    // Application environment: local | production
    'app_env' => 'production',
    'app_debug' => false,

    // Database
    'db_host' => 'localhost',
    'db_name' => 'jaxe_ops',
    'db_user' => 'root',
    'db_pass' => '',

    // Schema automation
    'auto_sync_schema' => false,

    // Upload directory used by the current asset delivery layer.
    'uploads_path' => __DIR__ . '/../public/uploads',

    // Optional webhooks and notification settings
    'publication_api_webhook' => '',
    'kpi_collection_api_webhook' => '',
    'mail_from_email' => '',
    'mail_from_name' => 'Jaxe Ops',
    'validation_notification_emails' => '',

    // Security/session
    'session_cookie_secure' => true,
    'session_cookie_samesite' => 'Lax',

    // If omitted, config/instance.secrets.php is generated automatically.
    // 'app_encryption_key' => 'put-a-long-random-secret-here',
];
