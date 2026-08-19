<?php

namespace App\Auth\Idp;

use App\Models\Provider;

class IdpProviderResolver
{
    public function resolve(): ?Provider
    {
        return Provider::find(config("idp.provider_id"));
    }

    public function isUsable(?Provider $provider): bool
    {
        return $provider !== null && !empty($provider->secret_key);
    }
}
