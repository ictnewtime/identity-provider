<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `GET admin/v1/users/roles`: i ruoli che hanno gli utenti indicati, senza ripetizioni.
 *
 * La modale "Associa ruoli" li usa per precompilare i ruoli degli utenti selezionati: basta che
 * un utente abbia il ruolo perché compaia, e un ruolo condiviso compare una volta sola.
 *
 * Le rotte reali portano `authenticated` + `role:admin`: qui si passa oltre, come in
 * ProviderUserRoleDeleteTest, perche' quei due sono coperti da AuthenticatedTest.
 */
class UsersRolesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = "/admin/v1/users/roles";

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = Provider::forceCreate([
            "domain" => "localhost",
            "url" => "http://localhost",
            "protocol" => "http",
            "secret_key" => Str::random(32),
            "logoutUrl" => "http://localhost/logout",
            "name" => "IDP",
        ]);
    }

    private function assign(User $user, Role $role): ProviderUserRole
    {
        return ProviderUserRole::create([
            "provider_id" => $this->provider->id,
            "user_id" => $user->id,
            "role_id" => $role->id,
        ]);
    }

    private function usersRoles(array $userIds)
    {
        return $this->withoutMiddleware()->getJson(self::URL . "?" . http_build_query(["user_ids" => $userIds]));
    }

    public function test_returns_the_roles_of_all_users_without_duplicates(): void
    {
        [$anna, $bruno, $carla] = User::factory()->count(3)->create(["enabled" => 1]);
        $shared = Role::create(["name" => "editor", "provider_id" => $this->provider->id]);
        $onlyAnna = Role::create(["name" => "admin", "provider_id" => $this->provider->id]);
        $onlyCarla = Role::create(["name" => "viewer", "provider_id" => $this->provider->id]);

        $this->assign($anna, $shared);
        $this->assign($bruno, $shared);
        $this->assign($anna, $onlyAnna);
        // carla non e' fra gli utenti richiesti: il suo ruolo non deve comparire
        $this->assign($carla, $onlyCarla);

        $response = $this->usersRoles([$anna->id, $bruno->id])->assertOk()->assertJsonCount(2);

        $this->assertEqualsCanonicalizing([$shared->id, $onlyAnna->id], array_column($response->json(), "id"));
        $response->assertJsonFragment([
            "id" => $shared->id,
            "name" => "editor",
            "provider_id" => $this->provider->id,
            "provider_name" => "IDP",
        ]);
    }

    public function test_a_single_user_gets_all_their_roles(): void
    {
        $anna = User::factory()->create(["enabled" => 1]);
        $this->assign($anna, Role::create(["name" => "editor", "provider_id" => $this->provider->id]));
        $this->assign($anna, Role::create(["name" => "admin", "provider_id" => $this->provider->id]));

        $this->usersRoles([$anna->id])->assertOk()->assertJsonCount(2);
    }

    public function test_deleted_associations_and_deleted_roles_are_ignored(): void
    {
        [$anna, $bruno] = User::factory()->count(2)->create(["enabled" => 1]);
        $softDeletedLink = Role::create(["name" => "editor", "provider_id" => $this->provider->id]);
        $deletedRole = Role::create(["name" => "admin", "provider_id" => $this->provider->id]);

        $this->assign($anna, $softDeletedLink)->delete();
        $this->assign($bruno, $softDeletedLink)->delete();
        $this->assign($anna, $deletedRole);
        $this->assign($bruno, $deletedRole);
        $deletedRole->delete();

        $this->usersRoles([$anna->id, $bruno->id])->assertOk()->assertExactJson([]);
    }

    public function test_the_query_count_does_not_grow_with_the_users(): void
    {
        $users = User::factory()->count(20)->create(["enabled" => 1]);
        $role = Role::create(["name" => "editor", "provider_id" => $this->provider->id]);
        $users->each(fn($user) => $this->assign($user, $role));

        DB::enableQueryLog();
        $this->usersRoles($users->pluck("id")->all())->assertOk()->assertJsonCount(1);

        // una query per le associazioni, una per i ruoli, una per i provider: niente N+1
        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
    }

    /** Un utente con `$count` ruoli distinti, inseriti in blocco e non uno alla volta. */
    private function userWithRoles(int $count): User
    {
        DB::table("roles")->insert(
            array_map(fn($i) => ["name" => "role_{$i}", "provider_id" => $this->provider->id], range(1, $count)),
        );
        $user = User::factory()->create(["enabled" => 1]);
        $now = now();
        foreach (Role::pluck("id")->chunk(250) as $chunk) {
            DB::table("provider_user_roles")->insert(
                $chunk
                    ->map(
                        fn($roleId) => [
                            "provider_id" => $this->provider->id,
                            "user_id" => $user->id,
                            "role_id" => $roleId,
                            "created_at" => $now,
                            "updated_at" => $now,
                        ],
                    )
                    ->values()
                    ->all(),
            );
        }
        return $user;
    }

    public function test_too_many_roles_is_an_error_not_a_truncated_list(): void
    {
        // uno oltre il massimo: tagliare l'elenco applicherebbe in silenzio solo una parte dei ruoli
        $user = $this->userWithRoles(1001);

        $this->usersRoles([$user->id])
            ->assertStatus(422)
            ->assertJson(["code" => "too_many_roles", "max" => 1000]);
    }

    public function test_exactly_the_maximum_is_still_returned(): void
    {
        $user = $this->userWithRoles(1000);

        $this->usersRoles([$user->id])->assertOk()->assertJsonCount(1000);
    }

    public function test_user_ids_are_required_and_bounded(): void
    {
        $this->withoutMiddleware()->getJson(self::URL)->assertStatus(422)->assertJsonValidationErrors("user_ids");

        $this->usersRoles(range(1, 501))->assertStatus(422)->assertJsonValidationErrors("user_ids");
    }
}
