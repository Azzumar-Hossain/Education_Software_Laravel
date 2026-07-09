<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Spatie Roles
        $roles = ['super_admin', 'admin', 'teacher', 'student', 'parent'];
        
        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        // 2. Create a default Super Admin user
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@edusphere.com',
            'password' => Hash::make('password'), // Default password is 'password'
            'type' => 'super_admin',
        ]);

        // 3. Assign the Spatie role to the user
        $admin->assignRole('super_admin');
    }
}
