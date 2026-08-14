<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('⚠️  No tenants found. Run TenantSeeder before OrganizationSeeder.');
            return;
        }

        $organizationTypes = ['vendor', 'buyer', 'partner', 'reseller', 'distributor'];
        $industryLabels = ['Logistics', 'Manufacturing', 'Retail', 'Healthcare', 'Media', 'Automotive', 'Finance'];

        foreach ($tenants as $tenant) {
            $existingOrganizations = $tenant->organizations()->count();
            $targetPerTenant = 3;

            for ($index = 1; $index <= $targetPerTenant; $index++) {
                $name = sprintf(
                    '%s %s',
                    fake()->company(),
                    $industryLabels[array_rand($industryLabels)]
                );

                $slug = Str::slug("{$name}-{$index}");

                Organization::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'slug' => $slug,
                ], [
                    'name' => $name,
                    'type' => fake()->randomElement($organizationTypes),
                    'code' => strtoupper(fake()->bothify('ORG-???-###')),
                    'email' => fake()->companyEmail(),
                    'phone' => fake()->phoneNumber(),
                    'website' => 'https://' . fake()->domainName(),
                    'is_active' => true,
                    'metadata' => [
                        'industry' => fake()->randomElement($industryLabels),
                    ],
                ]);
            }

            if ($existingOrganizations > 0 && $existingOrganizations < $targetPerTenant) {
                $this->command->info("ℹ️  Added missing organizations for tenant {$tenant->name}");
            }
        }

        $existingOrganizationCount = Organization::count();
        if ($existingOrganizationCount < 30) {
            $organizationsToCreate = 30 - $existingOrganizationCount;
            $tenantIds = $tenants->pluck('id')->all();

            for ($i = 0; $i < $organizationsToCreate; $i++) {
                $tenantId = $tenantIds[array_rand($tenantIds)];
                $name = sprintf(
                    '%s %s',
                    fake()->company(),
                    $industryLabels[array_rand($industryLabels)]
                );

                Organization::create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'slug' => Str::slug($name . '-' . ($i + 1)),
                    'type' => fake()->randomElement($organizationTypes),
                    'code' => strtoupper(fake()->bothify('ORG-???-###')),
                    'email' => fake()->companyEmail(),
                    'phone' => fake()->phoneNumber(),
                    'website' => 'https://' . fake()->domainName(),
                    'is_active' => true,
                    'metadata' => [
                        'industry' => fake()->randomElement($industryLabels),
                    ],
                ]);
            }
        }

        $this->command->info('✅ Organization seeding complete. Total organizations: ' . Organization::count());
    }
}
