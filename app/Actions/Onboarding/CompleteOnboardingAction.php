<?php

namespace App\Actions\Onboarding;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompleteOnboardingAction
{
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Create or find Tenant
            $tenant = Tenant::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['organization'],
                    'domain' => $data['slug'],
                    'is_active' => true,
                    'settings' => [
                        'store_name' => $data['store_name'],
                        'business_type' => $data['business_type'],
                        'website' => $data['website'] ?? null,
                    ],
                ]
            );

            // 2. Create Organization under the tenant
            $organization = Organization::firstOrCreate(
                ['slug' => $data['slug'], 'tenant_id' => $tenant->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => $data['organization'],
                    'type' => $data['business_type'],
                    'website' => $data['website'] ?? null,
                    'is_active' => true,
                    'metadata' => [
                        'store_name' => $data['store_name'],
                    ],
                ]
            );

            // 3. Link user to organization via membership
            Membership::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'organization_id' => $organization->id,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'role' => 'owner',
                    'is_primary' => true,
                    'is_active' => true,
                ]
            );

            // 4. Mark onboarding as complete
            $user->update([
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
            ]);

            $user->refresh();

            Log::info('Onboarding completed', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
            ]);

            return $user;
        });
    }
}
