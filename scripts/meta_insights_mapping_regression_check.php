<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__);
$collector = file_get_contents($root.'/app/helpers/SocialMetricsCollectorService.php');
$oauth = file_get_contents($root.'/app/controllers/SocialOAuthController.php');
$migration = file_get_contents($root.'/database/migrations/20260831_019_social_metric_availability.sql');
$model = file_get_contents($root.'/app/models/ReportingMetricModel.php');
$view = file_get_contents($root.'/app/views/reporting-metric/index.php');

$expectedMappings = [
    "'post_impressions'=>'impressions'",
    "'post_impressions_unique'=>'couverture'",
    "'post_clicks'=>'clics'",
    "'post_video_views','vues'",
    "'post_media_view','vues'",
    "'post_views','vues'",
    "'reach'=>'couverture'",
    "'views'=>'vues'",
    "'saved'=>'sauvegardes'",
    "'shares'=>'partages'",
];
foreach ($expectedMappings as $mapping) {
    if (!str_contains($collector, $mapping)) throw new RuntimeException('Mapping Meta absent: '.$mapping);
}
foreach (['impressions','couverture','vues','clics'] as $metric) {
    if (!preg_match('/MODIFY '.preg_quote($metric,'/').' INT NULL/', $migration)) throw new RuntimeException('Colonne non nullable: '.$metric);
}
if (!str_contains($collector, "'status'=>'unavailable'")) throw new RuntimeException('Disponibilité non tracée.');
if (!str_contains($oauth, "'read_insights'")) throw new RuntimeException('La collecte des insights Meta exige read_insights.');
if (!str_contains($view, 'Métrique indisponible')) throw new RuntimeException('État indisponible non affiché.');
if (str_contains($model, 'SUM(COALESCE(rm.couverture, 0))')) throw new RuntimeException('Une couverture indisponible ne doit pas devenir un zéro réel.');
if (str_contains($model, 'AVG(COALESCE(rm.ctr, 0))')) throw new RuntimeException('Un CTR indisponible ne doit pas devenir un zéro réel.');
if (!str_contains($view, '$metricPercent')) throw new RuntimeException('Les taux indisponibles doivent être affichés avec un tiret.');

echo "OK: mapping Meta, vues vidéo et distinction zéro/indisponible.\n";
