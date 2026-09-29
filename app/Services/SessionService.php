<?php

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SessionService
{
    /**
     * Gestisce l'upsert della sessione in modo sicuro (senza sovrascrivere l'UUID).
     */
    public function upsertSession(
        $user_id,
        $provider_id,
        $ip_address,
        $user_agent,
        $token,
        $refresh_token = null,
        ?Carbon $expires_at = null,
    ) {
        // Cerchiamo la sessione
        $session = Session::where("user_id", $user_id)->where("provider_id", $provider_id)->first();

        if ($session) {
            Log::debug("[SESSION] riga aggiornata", [
                "session_id" => $session->id,
                "user_id" => $user_id,
                "provider_id" => $provider_id,
                "expires_at" => $expires_at?->toDateTimeString(),
                "aveva_refresh_token" => !empty($session->refresh_token),
            ]);

            // Aggiorniamo se esiste (mantenendo lo stesso UUID)
            $session->update([
                "ip_address" => $ip_address,
                "user_agent" => $user_agent,
                "token" => $token,
                "refresh_token" => $refresh_token,
                "expires_at" => $expires_at,
                "last_activity" => now(),
            ]);
        } else {
            // Creiamo se non esiste (generando l'UUID)
            $session = Session::create([
                "id" => (string) Str::uuid(),
                "user_id" => $user_id,
                "provider_id" => $provider_id,
                "ip_address" => $ip_address,
                "user_agent" => $user_agent,
                "token" => $token,
                "refresh_token" => $refresh_token,
                "expires_at" => $expires_at,
                "last_activity" => now(),
            ]);

            Log::debug("[SESSION] riga creata", [
                "session_id" => $session->id,
                "user_id" => $user_id,
                "provider_id" => $provider_id,
                "expires_at" => $expires_at?->toDateTimeString(),
            ]);

            // TODO da gestire
            // $provider = Provider::find($provider_id);
            // $user = User::find($user_id);
            // LogExternal::logToLogService($user->username, "login", $ip_address, $provider->name);
        }

        return $session;
    }

    /**
     * Recupera o rigenera il token al login.
     */
    /**
     * L'app token per un utente su un provider, staccandone **sempre uno nuovo**.
     *
     * PERCHE' SEMPRE NUOVO: fino al 2026-08-28 questa funzione restituiva il token **salvato** se la
     * riga non era scaduta, e funzionava per un motivo fragile — riga e token scadevano insieme, quindi
     * «riga viva» implicava «token valido». Da allora la riga dura quanto il **master** token (otto ore)
     * e l'app token resta a trenta minuti: quell'implicazione non vale piu', e riusare il token salvato
     * significherebbe restituirne uno scaduto per sette ore e mezza. Rigenerare costa una firma.
     *
     * @param string|null $masterToken il master token che ha autorizzato la richiesta: finisce in
     * `refresh_token`, ed e' cio' che la riga rappresenta.
     * @param bool $canCreate se **creare** una sessione che non c'e'.
     *
     * PERCHE' `canCreate` ESISTE: dopo che un amministratore ha revocato una sessione, «la riga non
     * c'e'» e «la riga non c'e' ancora» sono indistinguibili — e la seconda deve poter creare, la
     * prima no. Chi **rinnova** passa `false`: se la riga e' sparita, la revoca deve valere.
     *
     * ATTENZIONE, e' una trappola gia' scattata: passare `false` **anche dall'exchange** chiude
     * l'unica porta da cui una sessione nasce oggi, e rende **impossibile il primo accesso** a
     * un'applicazione. L'exchange potra' passare `false` solo quando sara' il
     * **login** a creare la sessione del provider di destinazione.
     */
    public function getValidProviderToken(
        $user,
        $provider_id,
        $ip_address,
        $user_agent,
        TokenProviderService $tokenService,
        ?string $masterToken = null,
        bool $canCreate = true,
    ) {
        Log::debug("[SESSION] getValidProviderToken", [
            "user_id" => $user->id ?? null,
            "provider_id" => $provider_id,
            "ip_address" => $ip_address,
            "master_token" => self::tokenFingerprint($masterToken),
            "can_create" => $canCreate,
        ]);

        if (!$canCreate) {
            $existing = Session::where("user_id", $user->id)->where("provider_id", $provider_id)->first();

            if (!$existing) {
                Log::warning(
                    __("session.error.renew_refused.no_session", [
                        "userId" => $user->id,
                        "providerId" => $provider_id,
                    ]),
                );
                return null;
            }
        }

        // Controllo centralizzato: Abilitazione + Ruoli per il provider specifico
        if (!$user->hasAccessToProvider($provider_id)) {
            Log::warning(
                "Accesso negato: Utente ID {$user->id} disabilitato o senza ruoli per Provider {$provider_id}.",
            );
            return null;
        }

        $token = $tokenService->generateAppToken($user, $provider_id);

        if (!$token) {
            Log::error("[SESSION] App token non generato", [
                "user_id" => $user->id,
                "provider_id" => $provider_id,
                "motivo" => "generateAppToken() ha restituito null: provider inesistente o senza secret_key",
            ]);
            return null;
        }

        // La riga rappresenta il MASTER token, non l'app token: dura quanto lui.
        $expiresAt = self::masterTokenExpiresAt($masterToken, $tokenService);

        $session = $this->upsertSession(
            $user->id,
            $provider_id,
            $ip_address,
            $user_agent,
            $token,
            $masterToken,
            $expiresAt,
        );

        Log::debug("[SESSION] App token staccato", [
            "user_id" => $user->id,
            "provider_id" => $provider_id,
            "app_token" => self::tokenFingerprint($token),
            "master_token" => self::tokenFingerprint($masterToken),
            "expires_at" => $expiresAt->toDateTimeString(),
            "session_id" => $session->id ?? null,
        ]);

        return $token;
    }

    /**
     * La riga «del master token»: quella senza provider.
     *
     * E' il modello dello scambio (`api/v1/token/exchange`): una riga per utente, che rappresenta l'essere entrati — non
     * l'essere entrati in una certa applicazione. Il marcatore e' `provider_id IS NULL`, e serve un
     * `whereNull`: `where("provider_id", null)` in SQL **non trova mai niente**, perche' `= NULL` non
     * e' vero nemmeno per un valore nullo.
     */
    /**
     * Quando scade la riga di un master token: all'`exp` del token stesso, non a "adesso + durata".
     *
     * Prima si calcolava `now() + durata` in un momento diverso dall'emissione: la riga e il token
     * non coincidevano di almeno un secondo, e di ore quando il redirect SSO riusa un token valido
     * preso dal cookie.
     *
     * Il claim si legge SENZA verificare la firma: qui arrivano solo token gia' verificati a monte
     * (emessi ora, o passati da `VerifyMasterToken` / `masterTokenFor()`), e la scadenza non e' una
     * decisione di accesso. Senza token o senza `exp` leggibile si torna alla durata configurata.
     */
    public static function masterTokenExpiresAt(
        ?string $masterToken,
        ?TokenProviderService $tokenService = null,
    ): Carbon {
        $parts = $masterToken ? explode(".", $masterToken) : [];
        if (count($parts) === 3) {
            $claims = json_decode((string) base64_decode(strtr($parts[1], "-_", "+/")), true);
            if (is_array($claims) && is_numeric($claims["exp"] ?? null)) {
                // `setTimestamp` su `now()` e non `createFromTimestamp`: quello crea in UTC, e la
                // colonna si scrive nel fuso dell'applicazione.
                return now()->setTimestamp((int) $claims["exp"]);
            }
        }

        return now()->addSeconds(($tokenService ?? new TokenProviderService())->getMasterTokenExpiredAt());
    }

    public function masterSessionFor($userId): ?Session
    {
        return Session::where("user_id", $userId)->whereNull("provider_id")->first();
    }

    /**
     * Scrive o aggiorna la riga del master token.
     *
     * Non passa da `upsertSession()` per la ragione appena detta: quella cerca con `where`, e con un
     * provider nullo creerebbe una riga nuova a ogni chiamata.
     */
    public function upsertMasterSession(
        $userId,
        $ipAddress,
        $userAgent,
        string $masterToken,
        ?Carbon $expiresAt = null,
    ): Session {
        $session = $this->masterSessionFor($userId);
        $expiresAt = $expiresAt ?? self::masterTokenExpiresAt($masterToken);

        if ($session) {
            $session->update([
                "ip_address" => $ipAddress,
                "user_agent" => $userAgent,
                "refresh_token" => $masterToken,
                "expires_at" => $expiresAt,
                "last_activity" => now(),
            ]);

            Log::debug("[SESSION] riga del master token aggiornata", [
                "session_id" => $session->id,
                "user_id" => $userId,
                "master_token" => self::tokenFingerprint($masterToken),
            ]);

            return $session;
        }

        $session = Session::create([
            "id" => (string) Str::uuid(),
            "user_id" => $userId,
            "provider_id" => null,
            "ip_address" => $ipAddress,
            "user_agent" => $userAgent,
            // `token` non e' nullable e questa riga non ha un app token: lo scambio non ne tiene traccia
            // qui, la tiene negli `audits`.
            "token" => "",
            "refresh_token" => $masterToken,
            "expires_at" => $expiresAt,
            "last_activity" => now(),
        ]);

        Log::info("[SESSION] riga del master token creata", [
            "session_id" => $session->id,
            "user_id" => $userId,
            "expires_at" => $expiresAt->toDateTimeString(),
        ]);

        return $session;
    }

    /**
     * Apre la sessione del provider di destinazione al login.
     *
     * DUE PUNTI D'INGRESSO, e servono tutti e due: il login esplicito con `provider_id`, e il SSO
     * trasparente di chi e' gia' dentro e apre una seconda applicazione ore dopo. Con uno solo, il
     * secondo caso resterebbe senza riga.
     *
     * NON BLOCCA IL REDIRECT: se la creazione fallisce, l'utente arriva all'applicazione come prima e
     * il motivo sta nel log. Un login che si ferma perche' non ha potuto scrivere una riga sarebbe un
     * danno piu' grande del difetto che questa riga chiude.
     */
    public function openProviderSession(
        $user,
        $providerId,
        $ipAddress,
        $userAgent,
        ?string $masterToken = null,
    ): ?string {
        try {
            // La riga «del master token», che e' quella che usa lo scambio. Si scrive qui e
            // non all'exchange per la stessa ragione della riga per provider: se la creasse
            // l'exchange, l'exchange non potrebbe far valere una revoca.
            if ($masterToken) {
                $this->upsertMasterSession($user->id, $ipAddress, $userAgent, $masterToken);
            }

            // La riga PER PROVIDER la scrive il login solo per l'IdP, che ne ha bisogno per se':
            // `IdpSessionValidator::isAlive()` cerca la sessione **per app token**, quindi senza quella
            // riga ogni navigazione nell'IdP fallirebbe.
            //
            // Per le applicazioni esterne no: lo scambio lavora sulla riga del master token e non
            // chiede mai una riga per provider (dal 2026-09 la vecchia v1, che la chiedeva, non c'e' piu').
            if ((string) $providerId !== (string) config("idp.provider_id")) {
                Log::debug("[LOGIN] provider esterno: nessuna riga per provider, basta quella del master token.", [
                    "user_id" => $user->id ?? null,
                    "provider_id" => $providerId,
                ]);

                return null;
            }

            $token = $this->getValidProviderToken(
                $user,
                $providerId,
                $ipAddress,
                $userAgent,
                new TokenProviderService(),
                $masterToken,
            );

            if (!$token) {
                Log::warning("[LOGIN] sessione non aperta: l'utente non ha accesso al provider.", [
                    "user_id" => $user->id ?? null,
                    "provider_id" => $providerId,
                ]);
            }

            return $token;
        } catch (\Throwable $e) {
            // Il redirect non si blocca: l'utente entra, e qui resta scritto perche' la riga manca.
            Log::error("[LOGIN] apertura della sessione fallita: " . $e->getMessage(), [
                "user_id" => $user->id ?? null,
                "provider_id" => $providerId,
            ]);

            return null;
        }
    }

    /**
     * L'impronta di un token per i log: le ultime otto lettere e la lunghezza.
     *
     * I log di questa applicazione **escono dalla macchina** — `LOG_SERVICE_URL` in `.env` li manda a un
     * servizio esterno — e un JWT firmato e non scaduto e' una credenziale al portatore: chi lo legge
     * **e'** quell'utente. L'impronta basta per seguire un token attraverso il flusso, che e' cio' per
     * cui questi log esistono, e non e' utilizzabile da nessuno.
     */
    public static function tokenFingerprint(?string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        return "…" . substr($token, -8) . " (" . strlen($token) . " car.)";
    }

    /**
     * L'URL con il parametro `token` sostituito dalla sua impronta, per scriverlo nei log.
     *
     * Il redirect SSO porta il master token in `?token=`: scritto intero nel log, chiunque legga i
     * log poteva usarlo fino alla scadenza (8 ore). L'impronta basta a riconoscerlo.
     */
    public static function redactTokenInUrl(?string $url): ?string
    {
        if (empty($url)) {
            return $url;
        }

        return preg_replace_callback(
            "/([?&]token=)([^&#]*)/",
            fn($m) => $m[1] . self::tokenFingerprint(urldecode($m[2])),
            $url,
        );
    }

    /**
     * Verifica la sessione per la chiamata middleware dell'extension.
     * Ritorna un array con status HTTP e l'eventuale nuovo token.
     */
    public function validateSession($providerId, $clientId, $user_agent, bool $isApi)
    {
        $session = Session::where("user_id", $clientId)->where("provider_id", $providerId)->first();

        // Se la sessione non esiste, ritorniamo 404
        if (!$session) {
            Log::warning("[SESSION] validateSession: nessuna riga", [
                "user_id" => $clientId,
                "provider_id" => $providerId,
            ]);
            return ["status" => 404];
        }

        // Se la sessione è scaduta la eliminiamo e ritorniamo 404
        if ($session->expires_at && !$session->expires_at->isFuture()) {
            Log::warning("[SESSION] validateSession: riga scaduta, la cancello", [
                "session_id" => $session->id,
                "user_id" => $clientId,
                "provider_id" => $providerId,
                "expires_at" => $session->expires_at->toDateTimeString(),
            ]);
            $session->delete();
            return ["status" => 404];
        }

        // Valida: se lo User Agent è lo stesso oppure facciamo un check con l'API
        // consideriamo la sessione valida
        if ($isApi || empty($user_agent) || $session->user_agent === $user_agent) {
            $session->last_activity = now();
            $session->save();
            return ["status" => 200, "token" => null];
        }

        // Se cambia lo USER AGENT, allora è un cambio dispositivo/browser e termino la sessione
        if ($session->user_agent !== $user_agent) {
            Log::warning(
                "Rilevato cambio User Agent sul Provider ID {$providerId} con User ID {$clientId}. Termino la sessione.",
            );
            $session->delete();
            return ["status" => 404];
        }

        return ["status" => 404];
    }

    public function destroySession(int $userId, int $providerId): bool
    {
        $session = Session::where("user_id", $userId)->where("provider_id", $providerId)->first();

        if ($session) {
            $session->delete();
            return true;
        }

        return false;
    }

    public static function destroyAllUserSessions(int $userId)
    {
        $sessions = Session::where("user_id", $userId)->get();

        foreach ($sessions as $session) {
            $session->delete();
        }
    }
}
