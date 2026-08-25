<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/config/config.php';
$pdo=Database::getConnection();$pending=MigrationRunner::pendingFiles($pdo);
echo 'Pending migrations: '.count($pending).PHP_EOL;foreach($pending as$file)echo ' - '.$file.PHP_EOL;
if(!in_array('--apply',$argv,true)){echo "Dry run only. Use --apply to backup and migrate.\n";exit(0);}
if(!$pending){echo "Database already up to date.\n";exit(0);}
$backup=DatabaseBackupService::create($pdo,'pre-deploy');echo 'Backup: '.$backup.PHP_EOL;
MigrationRunner::runIfNeeded($pdo);echo "Migrations applied successfully.\n";
