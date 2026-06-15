<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'user',        'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Create super admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@pingflow.test'],
            [
                'name'           => 'Super Admin',
                'password'       => Hash::make('password'),
                'storage_quota'  => 1073741824,
                'storage_used'   => 0,
            ]
        );
        $admin->syncRoles(['super_admin']);

        $this->command->info('✅ Super admin created: admin@pingflow.test / password');
    }
}