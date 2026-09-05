<?php
class SocialPublishingCapability {
    public static function canPublish(array$connection): bool {
        if(($connection['status']??'')!=='Connected'||empty($connection['last_validated_at']))return false;
        $scopes=(array)json_decode((string)($connection['scopes_json']??'[]'),true);$provider=(string)($connection['provider']??'');
        return match($provider){'facebook'=>in_array('pages_manage_posts',$scopes,true),'instagram'=>(bool)array_intersect(['instagram_content_publish','instagram_business_content_publish'],$scopes),'linkedin'=>in_array('w_member_social',$scopes,true),'youtube'=>in_array('https://www.googleapis.com/auth/youtube.upload',$scopes,true),default=>false};
    }
}
