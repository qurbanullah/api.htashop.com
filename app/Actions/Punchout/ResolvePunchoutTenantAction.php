<?php

namespace App\Actions\Punchout;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResolvePunchoutTenantAction
{
    public function handle(string $identifier): Tenant
    {
        $tenant = Tenant::query()
            ->where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if (! $tenant) {
            throw (new ModelNotFoundException())->setModel(Tenant::class, [$identifier]);
        }

        return $tenant;
    }
}
