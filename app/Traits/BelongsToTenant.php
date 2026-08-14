<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for a model.
     *
     * @return void
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Apply tenant scope if the user is authenticated and has a tenant_id,
            // or if relying on a dedicated TenantContext service/middleware.
            // Adjust to our actual auth logic (e.g., bypassing for super-admins).
            if (Auth::check()) {
                $user = Auth::user();

                // Example check to bypass for super-admins if necessary
                if (! $user->hasRole('super-admin') && !empty($user->tenant_id)) {
                    $builder->where($builder->getModel()->getTable() . '.tenant_id', $user->tenant_id);
                }
            }
        });

        static::creating(function (Model $model) {
            // Automatically assign tenant_id on creation if missing
            if (empty($model->tenant_id) && Auth::check() && !empty(Auth::user()->tenant_id)) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Helper to retrieve models without the tenant scope (e.g. for super-admin or system processes)
     */
    public static function withoutTenantScope()
    {
        return static::withoutGlobalScope('tenant');
    }
}
