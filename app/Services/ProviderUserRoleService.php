<?php

namespace App\Services;

use App\Models\ProviderUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProviderUserRoleService
{
    // return object
    public function getJwtTokenInfo($provider_id, $user_id): object
    {
        $user = User::find($user_id);
        $providerUser = ProviderUserRole::where("provider_id", $provider_id)
            ->where("user_id", $user->id)
            ->with("role")
            ->with("role:id,name")
            ->get()
            ->toArray();
        $providerUserRoles = [];
        foreach ($providerUser as $providerUserRole) {
            $providerUserRoles[] = $providerUserRole["role"];
        }
        // create object
        $tokenBody = (object) [
            "user" => [
                "id" => $user->id,
                "email" => $user->email,
                "name" => $user->name,
                "surname" => $user->surname,
                "username" => $user->username,
            ],
            "roles" => $providerUserRoles,
        ];
        return $tokenBody;
    }
}
