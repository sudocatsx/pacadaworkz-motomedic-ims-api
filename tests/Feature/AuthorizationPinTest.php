<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function pinUser(string $role, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => Role::where('role_name', $role)->firstOrFail()->id,
    ], $overrides));
}

test('eligible user can enroll a hashed authorization pin without exposing it', function () {
    $manager = pinUser('manager');

    $this->actingAs($manager, 'api')->putJson('/api/v1/auth/authorization-pin', [
        'pin' => '246810',
        'pin_confirmation' => '246810',
    ])->assertOk()->assertJsonMissing(['pin' => '246810']);

    expect(Hash::check('246810', $manager->fresh()->authorization_pin))->toBeTrue();

    $this->actingAs($manager, 'api')->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.authorization_pin_configured', true)
        ->assertJsonMissing(['authorization_pin' => $manager->fresh()->authorization_pin]);
});

test('manager can clear lower scope pin but cannot clear a peer pin', function () {
    $manager = pinUser('manager');
    $staff = pinUser('staff', ['authorization_pin' => Hash::make('123456')]);
    $peer = pinUser('manager', ['authorization_pin' => Hash::make('654321')]);

    $this->actingAs($manager, 'api')
        ->deleteJson("/api/v1/users/{$staff->id}/authorization-pin")
        ->assertOk()
        ->assertJsonPath('data.authorization_pin_configured', false);

    expect($staff->fresh()->authorization_pin)->toBeNull();

    $this->actingAs($manager, 'api')
        ->deleteJson("/api/v1/users/{$peer->id}/authorization-pin")
        ->assertForbidden();
});

test('staff cannot enroll an authorization pin', function () {
    $staff = pinUser('staff');

    $this->actingAs($staff, 'api')->putJson('/api/v1/auth/authorization-pin', [
        'pin' => '246810',
        'pin_confirmation' => '246810',
    ])->assertForbidden();

    expect($staff->fresh()->authorization_pin)->toBeNull();
});
