<?php

namespace App\Http\Middleware;

use App\Support\IdpCookies;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;
use Illuminate\Contracts\Encryption\Encrypter;
use App\Models\Provider;
use Illuminate\Support\Facades\Log;

class EncryptCookies extends Middleware
{
    protected $except = [];

    public function __construct(Encrypter $encrypter)
    {
        parent::__construct($encrypter);

        try {
            // Master Token
            $master_token_name = config("idp.jwt.master_token_name");
            $this->except[] = $master_token_name;

            // App2
            $providerIds = Provider::pluck("id");
            foreach ($providerIds as $id) {
                $this->except[] = IdpCookies::appTokenName($id);
            }
        } catch (\Exception $e) {
            Log::error("Verifica che il db sia migrato e con almeno un provider");
        }
    }
}
