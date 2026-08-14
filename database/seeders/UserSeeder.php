<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if we already have enough users
        $existingUsersCount = User::count();

        if ($existingUsersCount >= 100) {
            $this->command->info("Found {$existingUsersCount} existing users, skipping user creation.");
        } else {
            $usersToCreate = 100 - $existingUsersCount;
            // Create users to reach 100 total
            $users = User::factory($usersToCreate)->create();

            // Assign roles to the newly created users
            $this->assignRolesToUsers($users);

            $this->command->info("Created {$usersToCreate} additional users!");
        }

        // Create some specific test users with known credentials and specific roles
        $testUsers = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'super-admin',
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'manager',
            ],
            [
                'name' => 'Support Assistant',
                'email' => 'support@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'support-assistant',
            ],
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'client',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'client',
            ],
            [
                'name' => 'Bob Wilson',
                'email' => 'bob.wilson@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'client',
            ],
            [
                'name' => 'Alice Johnson',
                'email' => 'alice.johnson@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
            ],
            [
                'name' => 'Charlie Brown',
                'email' => 'charlie.brown@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'client',
            ],
        ];

        foreach ($testUsers as $userData) {
            $role = $userData['role'];
            unset($userData['role']); // Remove role from user data

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Assign role to the user
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        // Assign roles to existing users that don't have roles yet
        $this->assignRolesToExistingUsers();

        $this->command->info('✅ Created 100 users successfully with roles assigned!');
    }

    /**
     * Assign roles to newly created users
     */
    private function assignRolesToUsers($users): void
    {
        foreach ($users as $user) {
            // Skip if user already has a role
            if ($user->roles->count() > 0) {
                continue;
            }

            // Assign roles with distribution: 60% client, 15% admin, 10% manager, 10% support-assistant, 5% guest
            $rand = mt_rand(1, 100);

            if ($rand <= 60) {
                $user->assignRole('client');
            } elseif ($rand <= 75) {
                $user->assignRole('admin');
            } elseif ($rand <= 85) {
                $user->assignRole('manager');
            } elseif ($rand <= 95) {
                $user->assignRole('support-assistant');
            } else {
                $user->assignRole('guest');
            }
        }
    }

    /**
     * Assign roles to existing users that don't have roles
     */
    private function assignRolesToExistingUsers(): void
    {
        // Get users without any roles
        $usersWithoutRoles = User::doesntHave('roles')->get();

        if ($usersWithoutRoles->count() > 0) {
            $this->command->info("Assigning roles to {$usersWithoutRoles->count()} existing users without roles...");
            $this->assignRolesToUsers($usersWithoutRoles);
        }
    }
}
