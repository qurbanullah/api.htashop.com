<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $sampleTenants = [
            'Sample Tenant',
            'Shadow Labs',
            'Nova Enterprise',
            'Atlas Commerce',
            'Echo Solutions',
            'Pinnacle Dynamics',
            'Vertex Holdings',
            'Pulse Technologies',
            'Summit Systems',
            'Beacon Industries',
        ];

        foreach ($sampleTenants as $tenantName) {
            $slug = Str::slug($tenantName);

            Tenant::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $tenantName,
                'domain' => "{$slug}.example.com",
                'is_active' => true,
                'settings' => [
                    'currency' => fake()->randomElement(['USD', 'EUR', 'GBP', 'AUD', 'CAD']),
                    'locale' => fake()->randomElement(['en_US', 'en_GB', 'fr_FR', 'de_DE', 'es_ES']),
                ],
            ]);
        }

        $existingTenantCount = Tenant::count();

        if ($existingTenantCount < 10) {
            $tenantsToCreate = 10 - $existingTenantCount;

            for ($i = 0; $i < $tenantsToCreate; $i++) {
                $name = fake()->company() . ' ' . fake()->unique()->numberBetween(101, 999);
                Tenant::create([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'domain' => Str::slug($name) . '.example.com',
                    'is_active' => true,
                    'settings' => [
                        'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
                        'locale' => fake()->randomElement(['en_US', 'en_GB', 'es_ES']),
                    ],
                ]);
            }
        }

        $this->command->info('✅ Tenant seeding complete. Total tenants: ' . Tenant::count());
    }
}
