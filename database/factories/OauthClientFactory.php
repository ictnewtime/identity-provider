<?php

namespace Database\Factories;

use App\Models\OauthClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OauthClientFactory extends Factory
{
    /**
     * Il nome del modello corrispondente alla factory.
     *
     * @var string
     */
    protected $model = OauthClient::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => null,
            'name' => $this->faker->name,
            'secret' => Str::random(20),
            'redirect' => 'http://localhost:8081/auth/callback',
            'personal_access_client' => 0,
            'password_client' => 0,
            'revoked' => 0,
        ];
    }
}