<?php

namespace App\Auth\Idp;

use Illuminate\Http\Request;

class IdpTokenExtractor
{
    public function cookieName(): string
    {
        return "idp_token_" . config("idp.provider_id");
    }

    public function extract(Request $request): ?string
    {
        return $request->cookie($this->cookieName()) ?? $request->bearerToken();
    }

    public function missingTokenContext(Request $request): array
    {
        return [
            "cookie_specifico_cercato" => $this->cookieName(),
            "cookie_specifico_valore" => $request->cookie($this->cookieName())
                ? "Presente (ma vuoto?)"
                : "Totalmente Assente",
            "bearer_token_valore" => $request->bearerToken() ? "Presente (ma vuoto?)" : "Totalmente Assente",
            "user_agent" => $request->userAgent(),
            "ip_client" => $request->ip(),
        ];
    }
}
