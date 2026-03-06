<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Passport\HasApiTokens;
// use App\Models\UserRole;

//, OAuthenticatable
class User extends Authenticatable // implements JWTSubject
{
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

    // /**
    //  * Get the identifier that will be stored in the subject claim of the JWT.
    //  *
    //  * @return mixed
    //  */
    // public function getJWTIdentifier()
    // {
    //     return $this->getKey();
    // }

    // /**
    //  * Return a key value array, containing any custom claims to be added to the JWT.
    //  *
    //  * @return array
    //  */
    // public function getJWTCustomClaims()
    // {
    //     return [];
    // }
}
