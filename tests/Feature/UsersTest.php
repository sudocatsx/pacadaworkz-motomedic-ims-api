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

function usersModuleActor(string $roleName = 'admin'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

function usersRole(string $roleName = 'staff'): Role
{
    return Role::where('role_name', $roleName)->firstOrFail();
}

test('user can be updated with unchanged email and contact number', function () {
    $admin = usersModuleActor();
    $user = User::factory()->create([
        'role_id' => usersRole()->id,
        'email' => 'juan@example.com',
    ]);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/users/{$user->id}", [
            'name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'role_id' => usersRole()->id,
            'contact_number' => '09171234567',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'juan@example.com')
        ->assertJsonPath('data.contact_number', '09171234567')
        ->assertJsonPath('data.is_active', false);
});

test('user cannot be updated to another active users email', function () {
    $admin = usersModuleActor();
    $target = User::factory()->create(['role_id' => usersRole()->id]);
    $other = User::factory()->create([
        'role_id' => usersRole()->id,
        'email' => 'taken@example.com',
    ]);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/users/{$target->id}", [
            'email' => $other->email,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['email'], 'data');
});

test('user contact number must contain digits only', function () {
    $admin = usersModuleActor();
    $user = User::factory()->create(['role_id' => usersRole()->id]);

    $this->actingAs($admin, 'api')
        ->patchJson("/api/v1/users/{$user->id}", [
            'contact_number' => '+63 912 345 6789',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contact_number'], 'data');
});

test('user can be created with default password without password field', function () {
    config(['app.key' => config('app.key')]);

    $admin = usersModuleActor();

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            'role_id' => usersRole()->id,
            'contact_number' => '09998887777',
            'is_default_password' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.contact_number', '09998887777');

    $user = User::where('email', 'maria@example.com')->firstOrFail();
    expect(Hash::check(env('USER_DEFAULT_PASSWORD', 'Password@2025!'), $user->password))->toBeTrue();
});

test('custom password is required when creating without default password', function () {
    $admin = usersModuleActor();

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/users', [
            'name' => 'Pedro Santos',
            'first_name' => 'Pedro',
            'last_name' => 'Santos',
            'email' => 'pedro@example.com',
            'role_id' => usersRole()->id,
            'is_default_password' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password'], 'data');
});

test('user password can be reset with default password', function () {
    $admin = usersModuleActor();
    $user = User::factory()->create([
        'role_id' => usersRole()->id,
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/users/{$user->id}/reset-password", [
            'is_default_password' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check(env('USER_DEFAULT_PASSWORD', 'Password@2025!'), $user->fresh()->password))->toBeTrue();
});

test('custom password is required when resetting without default password', function () {
    $admin = usersModuleActor();
    $user = User::factory()->create(['role_id' => usersRole()->id]);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/users/{$user->id}/reset-password", [
            'is_default_password' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['new_password'], 'data');
});
