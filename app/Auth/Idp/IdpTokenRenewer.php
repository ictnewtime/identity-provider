<?php

namespace App\Auth\Idp;

use App\Models\User;
use App\Services\SessionService;
use App\Services\TokenProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

/**
 * Rinnovo trasparente dell'app token (`VDF13`).
 *
 * Quando l'app token scade — dopo trenta minuti — il master token e' quasi sempre ancora valido:
 * dura otto ore. Il meccanismo per scambiarlo **esisteva gia'** (`token/exchange`), ma dentro l'IdP
 * nessuno lo chiamava, e l'utente veniva rispedito al login. Qui lo si chiama.
 *
 * Cosa il rinnovo **non** allarga:
 * - il vincolo IP + user agent resta quello di `getValidProviderToken()`;
 * - `canCreate: false` — una sessione revocata **non si ricrea**: il rinnovo e' per chi ce l'ha,
 *   non un secondo modo per ottenerla (`VDF14`);
 * - `expires_at` della sessione non si sposta: si rinnova la chiave, non il rapporto di fiducia.
 *
 * Il cookie lo emette questa classe, perche' e' l'unico punto che sa se il rinnovo e' riuscito.
 */
class IdpTokenRenewer
{
    public function __construct(
        private readonly IdpMasterTokenVerifier $masterTokenVerifier,
        private readonly IdpTokenExtractor $tokenExtractor,
    ) {}

    public function renew(Request $request): IdpRenewal
    {
        if (!$this->isNavigation($request)) {
            // un cookie in risposta a un `fetch` non viene raccolto come ci si aspetta, e
            // il rinnovo sembrerebbe funzionare a intermittenza. Chi chiama in API ha gia' la sua
            // strada: il 401 e poi `token/exchange`.
            Log::info("Rinnovo non tentato: la richiesta non e' di navigazione.");

            return IdpRenewal::notAttempted();
        }

        $masterToken = $request->cookie(config("idp.jwt.master_token_name"));
        $userId = $this->masterTokenVerifier->userIdFrom($masterToken);

        if (!$userId) {
            return IdpRenewal::masterTokenUnusable();
        }

        $user = User::find($userId);

        if (!$user) {
            Log::warning("Rinnovo impossibile: l'utente ID {$userId} del master token non esiste piu'.");

            return IdpRenewal::masterTokenUnusable();
        }

        $providerId = (string) config("idp.provider_id");
        $tokenService = new TokenProviderService();

        $token = (new SessionService())->getValidProviderToken(
            $user,
            $providerId,
            $request->ip(),
            $request->userAgent(),
            $tokenService,
            canCreate: false,
        );

        if (!$token) {
            // Il motivo — sessione assente, revocata o di un altro dispositivo — l'ha gia' scritto
            // nel log `getValidProviderToken()`.
            return IdpRenewal::refused();
        }

        $this->publish($request, $token, $providerId, $tokenService);

        Log::info("App token rinnovato per l'utente ID {$user->id} sul provider {$providerId}.");

        return IdpRenewal::renewed($token, $user);
    }

    /**
     * Il token nuovo va in due posti: nel cookie della risposta, e nella richiesta **in corso** —
     * senza il secondo, il resto di questa richiesta leggerebbe ancora quello scaduto.
     */
    private function publish(
        Request $request,
        string $token,
        string $providerId,
        TokenProviderService $tokenService,
    ): void {
        Cookie::queue($tokenService->cookieCretion($token, $providerId));
        $request->cookies->set($this->tokenExtractor->cookieName(), $token);
    }

    /**
     * Navigazione o chiamata API. Stesso criterio di `Authenticated::forceLogoutAndRedirect()`:
     * una richiesta Inertia vuole JSON ma **e'** navigazione, e va rinnovata.
     */
    private function isNavigation(Request $request): bool
    {
        return !$request->expectsJson() || $request->header("X-Inertia");
    }
}
