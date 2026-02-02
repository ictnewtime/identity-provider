<?php

namespace App\Services;

use App\Models\ProviderUserRole;
use App\Models\Provider;
use App\Models\User;
use App\Models\Role;

class ProviderUserRoleService
{
    // return object
    public function getJwtTokenInfo($provider_id, $user_id): object
    {
        // ottenere le info del user (id, email, name, surname)
        // e i ruoli (id, name)
        $user = User::find($user_id);
        $providerUser = ProviderUserRole::where("provider_id", $provider_id)
            ->where("user_id", $user->id)
            ->with("role")
            // da provider_user_roles.role_id faccio join
            // dalla tabella roles e seleziono id,name
            ->with("role:id,name")
            ->get()
            ->toArray();
        // providerUserRoles è un array con l' attributo role
        // che è un oggetto;
        // da estrarre gli oggetti role da questo array
        $providerUserRoles = [];
        foreach ($providerUser as $providerUserRole) {
            $providerUserRoles[] = $providerUserRole["role"];
        }
        // create object
        $tokenBody = (object) [
            "id" => $user->id,
            "email" => $user->email,
            "name" => $user->name,
            "surname" => $user->surname,
            "roles" => $providerUserRoles,
        ];
        return $tokenBody;
    }
}
