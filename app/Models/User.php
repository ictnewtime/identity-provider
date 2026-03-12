<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Passport\HasApiTokens;
use App\Models\Session;
use App\Models\ProviderUserRole;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

//, OAuthenticatable
// implements JWTSubject
class User extends Authenticatable implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;
    //HasApiTokens,
    use HasApiTokens, HasFactory, Notifiable;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ["username", "password", "email", "name", "surname", "is_verified", "enabled"];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ["password", "remember_token"];

    /**
     * The attributes that should be hiddend for auditing.
     */
    protected $auditExclude = ["password"];

    public function roles()
    {
        // $user_roles = $this->hasMany(UserRole::class, "user_id");
        $provider_id = config("app.provider_id");
        $provider_user_roles = ProviderUserRole::where("user_id", $this->id)
            ->where("provider_id", $provider_id)
            ->with("role")
            ->get();

        return $provider_user_roles;
    }

    /**
     * check if user has a specific role
     * the role can be a string(ex. admin) or an int(ex. 1)
     * @param $role
     */
    public function hasRole($role)
    {
        if (is_string($role)) {
            $role_id = Role::where("name", $role)->first()->id;
        } else {
            $role_id = $role;
        }
        $provider_id = config("app.provider_id");

        $provider_user_roles = ProviderUserRole::where("user_id", $this->id)
            ->where("provider_id", $provider_id)
            ->with("role")
            ->get();
        foreach ($provider_user_roles as $provider_user_role) {
            if ($provider_user_role->role->id == $role_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Restituisce la Query Builder per i ruoli di un determinato provider
     */
    public function providerRoles($providerId)
    {
        return \App\Models\ProviderUserRole::where("user_id", $this->id)->where("provider_id", $providerId);
    }

    /**
     * Semplice check: l'utente ha almeno un ruolo per questo provider?
     */
    public function hasAccessToProvider($providerId): bool
    {
        // Se l'utente è disabilitato globalmente, l'accesso è sempre negato
        if (isset($this->enabled) && !$this->enabled) {
            return false;
        }

        return $this->providerRoles($providerId)->exists();
    }

    /**
     * Verifica se l'utente è un Amministratore dell'IdP.
     * * @return bool
     */
    public function isAdmin(): bool
    {
        // 1. Se l'utente è disabilitato globalmente, non può essere admin
        if (isset($this->enabled) && !$this->enabled) {
            return false;
        }

        // 2. Recuperiamo gli ID dalle configurazioni
        $idpProviderId = config("idp.provider_id");
        $adminRoleId = config("role.admin_id");

        // 3. Verifica veloce e diretta a database (prestazioni massime)
        return \App\Models\ProviderUserRole::where("user_id", $this->id)
            ->where("provider_id", $idpProviderId)
            ->where("role_id", $adminRoleId)
            ->exists();
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // 1. Intercettiamo l'aggiornamento dell'utente
        static::updated(function ($user) {
            if ($user->wasChanged("enabled,password")) {
                Session::where("user_id", $user->id)->delete();
            }
        });

        // 2. Intercettiamo l'eliminazione dell'utente
        static::deleting(function ($user) {
            // Prima che l'utente venga cancellato dal DB, radiamo al suolo le sue sessioni
            Session::where("user_id", $user->id)->delete();
        });
    }
}
