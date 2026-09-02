<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../app/helpers/ReportPresentation.php';
require __DIR__.'/../app/helpers/InstagramMetricMapper.php';
function checkIg($ok,$message){if(!$ok)throw new RuntimeException($message);}
$data=InstagramMetricMapper::fromMedia(['like_count'=>0,'comments_count'=>3,'media_type'=>'VIDEO','media_product_type'=>'REELS']);
checkIg($data['likes']===0 && $data['commentaires']===3,'Real counts');
checkIg($data['_content_type']==='reel','Reel classification');
checkIg(InstagramMetricMapper::fromMedia([])['likes']===null,'Unavailable is not zero');
checkIg(InstagramMetricMapper::fromMedia(['media_type'=>'CAROUSEL_ALBUM'])['_content_type']==='carousel','Carousel classification');
class Controller {}
function config_env_value($name,$fallback){return $name==='META_OAUTH_SCOPES'?'pages_show_list':$fallback;}
require __DIR__.'/../app/controllers/SocialOAuthController.php';
$class=new ReflectionClass(SocialOAuthController::class);
$method=$class->getMethod('requestedScopes');$method->setAccessible(true);
$scopes=$method->invoke($class->newInstanceWithoutConstructor());
checkIg(in_array('instagram_manage_insights',$scopes,true),'Insights requested even with older env settings');
echo "OK: Instagram permission, missing/zero counts and media formats\n";
