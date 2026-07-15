<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('user seeder assigns roles by name instead of relying on numeric ids', function () {
    Role::query()->create([
        'role_name' => 'placeholder',
        'description' => 'Forces seeded roles away from assumed numeric IDs',
    ]);

    $this->seed(RoleSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::where('email', 'superadminpacada@gmail.com')->firstOrFail()->role->role_name)
        ->toBe('superadmin')
        ->and(User::where('email', 'asherjohn48@gmail.com')->firstOrFail()->role->role_name)
        ->toBe('admin')
        ->and(User::where('email', 'managerpacada@gmail.com')->firstOrFail()->role->role_name)
        ->toBe('manager')
        ->and(User::where('email', 'staffpacada@gmail.com')->firstOrFail()->role->role_name)
        ->toBe('staff');

    User::all()->each(function (User $user) {
        expect(Hash::check((string) config('auth.demo_user_password'), $user->password))->toBeTrue()
            ->and($user->is_active)->toBeTrue();
    });
});
