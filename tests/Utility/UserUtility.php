<?php

namespace Tests\Utility;

use App\Models\User;      // Assicurati che il namespace sia App\Models\User
use App\Models\Role;      // Assicurati che sia App\Models\Role
use App\Models\UserRole;  // Assicurati che sia App\Models\UserRole
use Illuminate\Support\Facades\Hash;

class UserUtility
{
    /**
     * Get admin user
     *
     * @return User
     */
    public static function getAdmin()
    {
        $role = Role::firstOrCreate(['name' => 'ADMIN_IDP']);

        $user = User::factory()->create([
            'is_verified' => true,
            'password' => Hash::make('secret'),
            'email' => 'admin_' . uniqid() . '@example.com', 
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id
        ]);

        return $user;
    }
}