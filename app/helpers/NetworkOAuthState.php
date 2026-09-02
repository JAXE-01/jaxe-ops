<?php
class NetworkOAuthState {
    public static function valid($saved,string $provider,int $tenant,int $user,int $now): bool {
        return is_array($saved) && ($saved['provider']??null)===$provider
            && (int)($saved['tenant_id']??0)===$tenant && (int)($saved['user_id']??0)===$user
            && (int)($saved['created']??0)<=$now && (int)($saved['created']??0)>$now-900;
    }
}
