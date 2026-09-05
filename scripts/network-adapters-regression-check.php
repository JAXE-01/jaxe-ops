<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../app/helpers/SocialPublishingCapability.php';
function adapterCheck(bool$ok,string$message): void {if(!$ok)throw new RuntimeException($message);}
$base=['status'=>'Connected','last_validated_at'=>'2026-09-05 00:00:00'];
adapterCheck(SocialPublishingCapability::canPublish($base+['provider'=>'linkedin','scopes_json'=>'["w_member_social"]']),'LinkedIn publish scope');
adapterCheck(!SocialPublishingCapability::canPublish($base+['provider'=>'linkedin','scopes_json'=>'["openid"]']),'LinkedIn missing scope');
adapterCheck(SocialPublishingCapability::canPublish($base+['provider'=>'youtube','scopes_json'=>'["https://www.googleapis.com/auth/youtube.upload"]']),'YouTube upload scope');
adapterCheck(!SocialPublishingCapability::canPublish($base+['provider'=>'tiktok','scopes_json'=>'["video.publish"]']),'TikTok must remain gated');
$publisher=file_get_contents(__DIR__.'/../app/helpers/SocialPublisherService.php');
$networkPublisher=file_get_contents(__DIR__.'/../app/helpers/NetworkPublisherService.php');
$collector=file_get_contents(__DIR__.'/../app/helpers/SocialMetricsCollectorService.php');
foreach(["'linkedin','youtube'=>NetworkPublisherService::publish",'YOUTUBE_PRIVACY_STATUS','upload/youtube/v3/videos']as$needle)adapterCheck(str_contains($publisher.$networkPublisher,$needle),'Publisher missing '.$needle);
foreach(['collectLinkedIn','collectYouTube','collectTikTok','viewCount','reactionSummaries','view_count']as$needle)adapterCheck(str_contains($collector,$needle),'Collector missing '.$needle);
echo "OK: LinkedIn/YouTube publishing gates and LinkedIn/YouTube/TikTok collectors.\n";
