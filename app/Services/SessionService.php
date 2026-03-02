<?php

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Str;

class SessionService
{
    public function createSession($user_id, $provider_id, $ip_address, $token, $refresh_token, $expires_at)
    {
        // id is uuid
        $session = new Session();
        $session->id = Str::uuid();
        $session->user_id = $user_id;
        $session->provider_id = $provider_id;
        $session->ip_address = $ip_address;
        $session->token = $token;
        $session->refresh_token = $refresh_token;
        $session->expires_at = $expires_at;
        $session->save();

        return $session;
    }
}
