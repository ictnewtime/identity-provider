<?php

namespace App\Auth\Idp;

use App\Models\User;

class IdpRenewal
{
    private function __construct(
        public readonly ?string $token,
        public readonly ?User $user,
        public readonly string $messageKey,
    ) {}

    public static function renewed(string $token, User $user): self
    {
        return new self($token, $user, "");
    }

    /** Rotta non di navigazione: il rinnovo non si tenta nemmeno. */
    public static function notAttempted(): self
    {
        return new self(null, null, "auth.token-expired");
    }

    /** Master token assente o scaduto: la sessione e' finita davvero. */
    public static function masterTokenUnusable(): self
    {
        return new self(null, null, "auth.renew-failed");
    }

    /** Master token valido, ma il rinnovo e' stato negato: sessione revocata o altro dispositivo. */
    public static function refused(): self
    {
        return new self(null, null, "auth.renew-refused");
    }

    public function succeeded(): bool
    {
        return $this->token !== null && $this->user !== null;
    }
}
