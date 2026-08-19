<?php

namespace App\Auth\Idp;

use App\Models\Session;

class IdpSessionValidator
{
    public function isAlive(string $token): bool
    {
        return Session::where("token", $token)->exists();
    }
}
