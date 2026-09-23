<?php

namespace App\Support\Tenant;

use App\Helpers\CacheHelper;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Central tenant resolution for inbox features.
 *
 * - Public intake endpoints (contact / feedback) resolve the storefront tenant
 *   from an `X-Tenant-Domain` / `X-Tenant-Slug` request header and fall back to
 *   the first active tenant (single-market deployments).
 * - Admin/staff endpoints are scoped through the authenticated user's primary
 *   active membership. `null` means global access (super-admin); `0` means the
 *   user is not bound to any tenant and must see no rows.
 */
class TenantContext
{
    public const DOMAIN_HEADER = 'X-Tenant-Domain';
    public const SLUG_HEADER = 'X-Tenant-Slug';

    /**
     * Resolve the tenant for a storefront request.
     */
    public static function fromRequest(?Request $request = null): ?Tenant
    {
        $request ??= request();

        $domain = trim((string) $request->header(self::DOMAIN_HEADER));
        if ($domain !== '') {
            $tenant = self::findByDomain($domain);
            if ($tenant) {
                return $tenant;
            }
        }

        $slug = trim((string) $request->header(self::SLUG_HEADER));
        if ($slug !== '') {
            $tenant = self::findBySlug($slug);
            if ($tenant) {
                return $tenant;
            }
        }

        return self::defaultTenant();
    }

    /**
     * Get the tenant id a public storefront submission should be attached to.
     */
    public static function publicTenantId(?Request $request = null): ?int
    {
        return self::fromRequest($request)?->id;
    }

    /**
     * Resolve the tenant scope for an admin/staff user.
     *
     * @return int|null null = global (super-admin), 0 = no tenant bound,
     *                  > 0 = the tenant id the user manages.
     */
    public static function adminScope(?User $user = null): ?int
    {
        $user ??= Auth::user();

        if (!$user) {
            return null;
        }

        if ($user->hasRole('super-admin')) {
            return null;
        }

        $membership = $user->memberships()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        return $membership?->tenant_id ?? 0;
    }

    /**
     * Find a tenant by its configured domain (cached).
     */
    public static function findByDomain(string $domain): ?Tenant
    {
        $normalized = strtolower(trim($domain));
        if ($normalized === '') {
            return null;
        }

        return CacheHelper::remember(
            ['tenants'],
            'tenant:domain:' . md5($normalized),
            3600,
            fn () => Tenant::query()
                ->where('domain', $normalized)
                ->where('is_active', true)
                ->first(),
        );
    }

    /**
     * Find a tenant by its slug (cached).
     */
    public static function findBySlug(string $slug): ?Tenant
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            return null;
        }

        return CacheHelper::remember(
            ['tenants'],
            'tenant:slug:' . $normalized,
            3600,
            fn () => Tenant::query()
                ->where('slug', $normalized)
                ->where('is_active', true)
                ->first(),
        );
    }

    /**
     * Fall back to the first active tenant (single-market default).
     * Deliberately uncached so test databases never read stale tenants.
     */
    public static function defaultTenant(): ?Tenant
    {
        return Tenant::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
