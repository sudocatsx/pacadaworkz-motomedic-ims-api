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

function profileSettingsUser(string $roleName = 'admin'): User
{
    $role = Role::where('role_name', $roleName)->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

test('user can update profile contact number with digits and leading zeroes', function () {
    $user = profileSettingsUser();

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/settings/profile', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => $user->email,
            'contact_number' => '09171234567',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.contact_number', '09171234567');

    expect($user->fresh()->contact_number)->toBe('09171234567');
});

test('profile contact number must contain digits only', function () {
    $user = profileSettingsUser();

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/settings/profile', [
            'contact_number' => '+63 912 345 6789',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contact_number']);
});

test('user can clear profile contact number', function () {
    $user = profileSettingsUser();
    $user->update(['contact_number' => '09171234567']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/settings/profile', [
            'contact_number' => null,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.contact_number', null);

    expect($user->fresh()->contact_number)->toBeNull();
});
