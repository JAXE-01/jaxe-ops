<?php
// Diagnostic local uniquement : aucune clé, aucun jeton, aucun appel réseau.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/config/config.php';
$providers = [
    'tiktok' => ['TIKTOK_CLIENT_KEY', 'TIKTOK_CLIENT_SECRET'],
    'linkedin' => ['LINKEDIN_CLIENT_ID', 'LINKEDIN_CLIENT_SECRET'],
    'youtube' => ['YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET'],
];
$report = [];
foreach ($providers as $provider => $keys) {
    $presence = [];
    foreach ($keys as $key) {
        $presence[$key] = trim((string) config_env_value($key, '')) !== '';
    }
    $report[$provider] = [
        'variables_presentes' => $presence,
        'identifiants_valides' => 'non testes : consentement OAuth requis',
        'adaptateur_oauth' => 'non implemente',
        'connexion_operationnelle' => false,
    ];
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
