<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        // First, seed roles and permissions
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // Create default test user first (only if it doesn't exist)
        if (!User::where('email', 'test@example.com')->exists()) {
            $testUser = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
            // Assign super-admin role to default test user
            $testUser->assignRole('super-admin');
            $this->command->info('✅ Created default test user with super-admin role');
        } else {
            $testUser = User::where('email', 'test@example.com')->first();
            // Ensure the test user has super-admin role
            if (!$testUser->hasRole('super-admin')) {
                $testUser->assignRole('super-admin');
                $this->command->info('✅ Assigned super-admin role to existing test user');
            } else {
                $this->command->info('ℹ️  Default test user already exists with super-admin role');
            }
        }

        // Run the user seeder to create 100 users
        $this->call([
            UserSeeder::class,
            PackageSeeder::class,
            FeatureSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            TicketSeeder::class,
            MeasurementSeeder::class,
            CurrencySeeder::class,
            DefinitionSeeder::class,
            TenantSeeder::class,
            OrganizationSeeder::class,
            ManufacturerSeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            VariantSeeder::class,
            OrderSeeder::class,
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
    }
}
