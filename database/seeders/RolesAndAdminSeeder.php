<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'employee', 'company'];

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        $email = env('SUPER_ADMIN_EMAIL');

        if (! $email) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'email_verified_at' => now(),
                'locale' => 'da',
                'is_active' => true,
            ],
        );

        $admin->assignRole('admin');
    }
}
