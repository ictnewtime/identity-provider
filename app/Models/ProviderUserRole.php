<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Provider;
use App\Models\User;
use App\Models\Role;
use App\Models\Session;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\CustomAuditable;

class ProviderUserRole extends Model
{
    use SoftDeletes;
    use CustomAuditable;

    protected $fillable = ["user_id", "provider_id", "role_id"];
    public $timestamps = true;

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    protected static function booted(): void
    {
        // 1. Intercettiamo Creazione e Aggiornamento
        static::saved(function ($providerUserRole) {
            $idpProviderId = config("idp.provider_id");

            // Distruggiamo la sessione SOLO se il ruolo modificato NON appartiene all'IdP
            if ($providerUserRole->provider_id != $idpProviderId) {
                Session::where("user_id", $providerUserRole->user_id)
                    ->where("provider_id", $providerUserRole->provider_id)
                    ->delete();
            }
        });

        // 2. Intercettiamo l'Eliminazione
        static::deleted(function ($providerUserRole) {
            $idpProviderId = config("idp.provider_id");

            // Distruggiamo la sessione SOLO se il ruolo eliminato NON appartiene all'IdP
            if ($providerUserRole->provider_id != $idpProviderId) {
                Session::where("user_id", $providerUserRole->user_id)
                    ->where("provider_id", $providerUserRole->provider_id)
                    ->delete();
            }
        });
    }
}
