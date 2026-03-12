<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Provider;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderUserRole extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

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
        // 1. Intercettiamo Creazione e Aggiornamento (quando gli diamo o cambiamo un ruolo)
        static::saved(function ($providerUserRole) {
            Session::where("user_id", $providerUserRole->user_id)
                ->where("provider_id", $providerUserRole->provider_id)
                ->delete();
        });

        // 2. Intercettiamo l'Eliminazione (quando gli togliamo del tutto un ruolo)
        static::deleted(function ($providerUserRole) {
            Session::where("user_id", $providerUserRole->user_id)
                ->where("provider_id", $providerUserRole->provider_id)
                ->delete();
        });
    }
}
