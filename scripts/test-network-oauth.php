<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/helpers/NetworkOAuthService.php';
require dirname(__DIR__).'/app/helpers/NetworkOAuthState.php';
require dirname(__DIR__).'/app/helpers/CryptoService.php';
$config=[];
function config_env_value($key,$default=''){global $config;return $config[$key]??$default;}
$count=0;
function check($ok,$message){global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function fails(callable $action){try{$action();}catch(RuntimeException $e){return true;}return false;}
foreach(['tiktok','linkedin','youtube'] as $provider){
    $d=NetworkOAuthService::definition($provider);
    check(fails(fn()=>(new NetworkOAuthService())->authorize($provider,'state',NetworkOAuthService::callbackUrl($provider))),'missing credentials');
    $config[$d['key']]='test-client';$config[$d['secret']]='test-secret';
    $url=(new NetworkOAuthService())->authorize($provider,'random-state',NetworkOAuthService::callbackUrl($provider));
    parse_str(parse_url($url,PHP_URL_QUERY),$query);
    check($query['state']==='random-state','state forwarded');
    check($query['redirect_uri']===NetworkOAuthService::callbackUrl($provider),'callback exact');
    check(!str_contains($url,'test-secret'),'secret absent from authorize URL');
    check($query['scope']===implode($provider==='tiktok'?',':' ',$d['scopes']),'scopes');
    $service=new NetworkOAuthService(function($method,$url,$body,$token)use($provider,$d){
        check($method==='POST'&&$url===$d['token'],'token endpoint');
        check($body['client_secret']==='test-secret','secret submitted server-side');
        check(isset($body[$provider==='tiktok'?'client_key':'client_id']),'client parameter');
        return ['access_token'=>'access','refresh_token'=>'refresh','expires_in'=>3600,'scope'=>implode(' ',$d['scopes']),'token_type'=>'Bearer'];
    });
    check($service->exchange($provider,'code',NetworkOAuthService::callbackUrl($provider))['access_token']==='access','code exchange');
    check($service->refresh($provider,'refresh')['access_token']==='access','refresh');
    check(fails(fn()=>$service->refresh($provider,'')),'missing refresh');
    $denied=new NetworkOAuthService(fn()=>['access_token'=>'access','scope'=>'unrelated']);
    check(fails(fn()=>$denied->exchange($provider,'code','https://example.test/callback')),'missing permission');
    $empty=new NetworkOAuthService(fn()=>[]);
    check(fails(fn()=>$empty->exchange($provider,'code','https://example.test/callback')),'missing token');
}
$config['TIKTOK_OAUTH_SCOPES']='user.info.basic,video.list';
check(NetworkOAuthService::scopes('tiktok')===['user.info.basic','video.list'],'configurable TikTok scopes');
$config['TIKTOK_OAUTH_SCOPES']='video.list';
check(fails(fn()=>NetworkOAuthService::scopes('tiktok')),'TikTok basic scope required');
$config['TIKTOK_OAUTH_SCOPES']='user.info.basic,video.publish';
check(fails(fn()=>NetworkOAuthService::scopes('tiktok')),'TikTok unimplemented scope rejected');
unset($config['TIKTOK_OAUTH_SCOPES']);
check(fails(fn()=>NetworkOAuthService::definition('evil')),'provider allowlist');
$config['YOUTUBE_REDIRECT_URI']='http://example.test/callback';
check(fails(fn()=>NetworkOAuthService::callbackUrl('youtube')),'reject http');
$config['YOUTUBE_REDIRECT_URI']='https://user:pass@example.test/callback';
check(fails(fn()=>NetworkOAuthService::callbackUrl('youtube')),'reject credentials in callback');
unset($config['YOUTUBE_REDIRECT_URI']);
$saved=['provider'=>'youtube','tenant_id'=>1,'user_id'=>2,'created'=>1000];
check(NetworkOAuthState::valid($saved,'youtube',1,2,1100),'valid state');
check(!NetworkOAuthState::valid($saved,'tiktok',1,2,1100),'provider mixing');
check(!NetworkOAuthState::valid($saved,'youtube',3,2,1100),'tenant mixing');
check(!NetworkOAuthState::valid($saved,'youtube',1,3,1100),'user mixing');
check(!NetworkOAuthState::valid($saved,'youtube',1,2,1900),'expiry');
check(!NetworkOAuthState::valid($saved,'youtube',1,2,900),'future state');
check(!NetworkOAuthState::valid(null,'youtube',1,2,1100),'missing state');
$fixtures=[
 'tiktok'=>['data'=>['user'=>['open_id'=>'tt-1','display_name'=>'TikTok test']]],
 'linkedin'=>['sub'=>'li-1','name'=>'LinkedIn test'],
 'youtube'=>['items'=>[['id'=>'yt-1','snippet'=>['title'=>'YouTube test']]]],
];
foreach($fixtures as $provider=>$fixture){
 $service=new NetworkOAuthService(function($method,$url,$body,$token)use($fixture){check($method==='GET'&&$token==='access','authenticated account request');return $fixture;});
 check($service->account($provider,'access')['id']!=='','account identity');
 check(fails(fn()=>(new NetworkOAuthService(fn()=>[]))->account($provider,'access')),'reject unidentified account');
}
check(fails(fn()=>(new NetworkOAuthService(fn()=>['items'=>[[],[]]]))->account('youtube','access')),'reject ambiguous channel');
check(fails(fn()=>NetworkOAuthService::encrypt('plain-token')),'fail closed without encryption');
define('APP_ENCRYPTION_KEY',str_repeat('test-key',8));
$cipher=NetworkOAuthService::encrypt('plain-token');
check($cipher!=='plain-token'&&CryptoService::decrypt($cipher)==='plain-token','encrypted roundtrip');
echo "OK: $count OAuth checks (mock providers, no real credentials)\n";
