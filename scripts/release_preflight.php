<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/config.php';
$checks=[];
$checks['schema_auto_sync_disabled']=!AUTO_SYNC_SCHEMA;
$checks['encryption_key_configured']=strlen((string)APP_ENCRYPTION_KEY)>=32&&APP_ENCRYPTION_KEY!=='change-me-in-production';
$checks['debug_disabled']=!APP_DEBUG;
$checks['smtp_encrypted']=in_array(strtolower((string)SMTP_SECURE),['tls','ssl'],true);
$rules=(string)file_get_contents(dirname(__DIR__).'/.htaccess');
$checks['apache_sensitive_paths_rules']=str_contains($rules,'STRAX_PRIVATE_PATHS');
try{
    $applied=Database::getConnection()->query('SELECT filename,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $checks['migrations_current']=true;
    foreach(glob(rtrim(MIGRATIONS_PATH,'/\\').'/*.sql')?:[] as $file){if(!isset($applied[basename($file)])||!hash_equals($applied[basename($file)],sha1_file($file))){$checks['migrations_current']=false;break;}}
}catch(Throwable $e){$checks['migrations_current']=false;}
echo json_encode(['environment'=>APP_ENV,'checks'=>$checks,'note'=>'Read-only configuration checks; does not prove Apache enforcement, backup restore or production readiness.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
