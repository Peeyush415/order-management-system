<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo logins (password: "password"); listed in the README.
     */
    public const DEMO_USERS = [
        ['name' => 'Admin User', 'email' => 'admin@oms.test', 'role' => 'admin'],
        ['name' => 'Manager One', 'email' => 'manager1@oms.test', 'role' => 'manager'],
        ['name' => 'Manager Two', 'email' => 'manager2@oms.test', 'role' => 'manager'],
        ['name' => 'Staff One', 'email' => 'staff1@oms.test', 'role' => 'staff'],
        ['name' => 'Staff Two', 'email' => 'staff2@oms.test', 'role' => 'staff'],
        ['name' => 'Staff Three', 'email' => 'staff3@oms.test', 'role' => 'staff'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        foreach (self::DEMO_USERS as $demo) {
            User::factory()
                ->create(['name' => $demo['name'], 'email' => $demo['email']])
                ->assignRole($demo['role']);
        }
    }
}
