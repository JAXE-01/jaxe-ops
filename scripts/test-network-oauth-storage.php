<?php
if(PHP_SAPI!=='cli')exit;
$dsn=getenv('STRAX_SQL_TEST_DSN');
if(!$dsn){fwrite(STDERR,"Provide an isolated STRAX_SQL_TEST_DSN.\n");exit(2);}
class Controller { public function currentUser(){return ['id'=>2];} }
class Database {public static PDO $db;public static function getConnection(){return self::$db;}}
class TenantGuard {public static function tenantId(){return 1;}}
define('APP_ENCRYPTION_KEY',str_repeat('isolated-test',5));
require __DIR__.'/../app/helpers/CryptoService.php';
require __DIR__.'/../app/helpers/NetworkOAuthService.php';
require __DIR__.'/../app/controllers/NetworkOAuthController.php';
$db=Database::$db=new PDO($dsn,getenv('STRAX_SQL_TEST_USER')?:'root',getenv('STRAX_SQL_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
// Session-local temporary table; real application records are never touched.
$db->exec("CREATE TEMPORARY TABLE social_connections (id INT PRIMARY KEY,tenant_id INT,client_id INT,provider VARCHAR(30),external_account_id VARCHAR(190),account_label VARCHAR(190),account_type VARCHAR(40),access_token_encrypted TEXT,refresh_token_encrypted TEXT,token_expires_at DATETIME,scopes_json JSON,metadata_json JSON,status VARCHAR(30),last_validated_at DATETIME,connected_by INT,UNIQUE(tenant_id,provider,external_account_id)) ENGINE=InnoDB");
$db->exec("INSERT INTO social_connections(id,tenant_id,client_id,provider,status) VALUES(1,1,10,'youtube','Pending'),(2,1,20,'youtube','Pending'),(3,2,30,'youtube','Pending')");
$class=new ReflectionClass(NetworkOAuthController::class);$controller=$class->newInstanceWithoutConstructor();$save=$class->getMethod('save');$save->setAccessible(true);
$row=fn($id)=>$db->query('SELECT * FROM social_connections WHERE id='.(int)$id)->fetch(PDO::FETCH_ASSOC);
$account=['id'=>'channel-1','name'=>'Test channel','type'=>'Channel'];
$tokens=['access_token'=>'access-1','refresh_token'=>'refresh-1','expires_in'=>3600];
$count=0;
function assertStorage($ok,$message){global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function rejectsStorage(callable $action){try{$action();}catch(RuntimeException $e){return true;}return false;}
$save->invoke($controller,$row(1),$account,$tokens);
$saved=$row(1);
assertStorage($saved['status']==='Connected','connected');
assertStorage($saved['access_token_encrypted']!=='access-1'&&CryptoService::decrypt($saved['access_token_encrypted'])==='access-1','encrypted access');
assertStorage(CryptoService::decrypt($saved['refresh_token_encrypted'])==='refresh-1','encrypted refresh');
assertStorage(abs(strtotime($saved['token_expires_at'])-time()-3600)<5,'expiry');
assertStorage(!json_decode($saved['metadata_json'],true)['analytics_enabled'],'no false analytics capability');
$save->invoke($controller,$row(1),$account,['access_token'=>'access-2','expires_in'=>3600]);
assertStorage(CryptoService::decrypt($row(1)['refresh_token_encrypted'])==='refresh-1','preserve refresh when omitted');
$save->invoke($controller,$row(1),$account,['access_token'=>'access-3','refresh_token'=>'refresh-2']);
assertStorage(CryptoService::decrypt($row(1)['refresh_token_encrypted'])==='refresh-2','rotate refresh');
assertStorage(rejectsStorage(fn()=>$save->invoke($controller,$row(2),$account,$tokens)),'duplicate rejected');
assertStorage($row(2)['status']==='Pending','duplicate rollback');
assertStorage(rejectsStorage(fn()=>$save->invoke($controller,$row(1),['id'=>'different','name'=>'Other','type'=>'Channel'],$tokens)),'identity swap rejected');
assertStorage(rejectsStorage(fn()=>$save->invoke($controller,$row(3),$account,$tokens)),'tenant isolation');
$stale=$row(2);$db->exec('UPDATE social_connections SET client_id=99 WHERE id=2');
assertStorage(rejectsStorage(fn()=>$save->invoke($controller,$stale,['id'=>'new','name'=>'New','type'=>'Channel'],$tokens)),'client reassignment rejected');
assertStorage(!$db->inTransaction(),'transaction cleanup');
echo "OK: $count OAuth storage checks (temporary MariaDB fixtures)\n";
