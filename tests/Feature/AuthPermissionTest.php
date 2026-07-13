<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function userForRole(string $roleName, string $password = 'password'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
        'password' => Hash::make($password),
    ]);
}

test('valid credentials can login, fetch current user, refresh, and logout', function () {
    $user = userForRole('superadmin', 'password');

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $login->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
        ]);

    expect($user->fresh()->last_login)->not->toBeNull();

    $accessToken = $login->json('data.access_token');
    $refreshToken = $login->json('data.refresh_token');

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.role.role_name', 'superadmin')
        ->assertJsonStructure([
            'data' => [
                'role' => [
                    'permissions',
                ],
            ],
        ]);

    $this->withHeader('Authorization', "Bearer {$refreshToken}")
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['access_token', 'new_access_token', 'token_type', 'expires_in'],
        ]);

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('invalid credentials return unauthorized', function () {
    $user = userForRole('superadmin', 'password');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(401)
        ->assertJsonPath('success', false);
});

test('roles route enforces auth and role permissions', function () {
    $admin = userForRole('admin');
    $staff = userForRole('staff');

    $this->getJson('/api/v1/roles')
        ->assertStatus(401);

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->actingAs($staff, 'api')
        ->getJson('/api/v1/roles')
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});
