<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Management of discount codes.
 *
 * Deliberately separate from `CouponService`, which is the *checkout* engine
 * (resolve, price, redeem, release). This class only reads and writes coupon
 * rows, so the rules deciding whether a code is usable keep exactly one home.
 *
 * Every read accepts an optional `CouponScope`. Admin passes none (they see
 * everything); the merchant service passes the merchant's own scope, which
 * narrows the very same queries rather than duplicating them.
 */
class CouponAdminService
{
    /** Columns the admin may sort by. Anything else falls back to `created_at`. */
    private const SORTABLE = ['code', 'value', 'used_count', 'starts_at', 'ends_at', 'created_at'];

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Coupon>
     */
    public function search(array $filters, ?CouponScope $scope = null): Builder
    {
        $search = trim((string) data_get($filters, 'search', ''));
        $type = data_get($filters, 'type');
        $active = data_get($filters, 'is_active');
        $sort = in_array(data_get($filters, 'sort'), self::SORTABLE, true)
            ? (string) data_get($filters, 'sort')
            : 'created_at';
        $order = strtolower((string) data_get($filters, 'order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $scopeFilter = data_get($filters, 'scope');
        $tenantFilter = data_get($filters, 'tenant_id');
        $organizationFilter = data_get($filters, 'organization_id');

        $query = Coupon::query()
            ->with(['tenant:id,name', 'organization:id,name'])
            ->withCount('redemptions')
            // A merchant only ever sees their own scope; the admin scope filters
            // below only bite when no scope was forced.
            ->when($scope !== null, fn (Builder $query) => $scope->constrain($query));

        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $inner) => $inner
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('label', 'like', '%'.$search.'%'));
            })
            ->when(filled($type), fn (Builder $query) => $query->where('type', $type))
            // `0` and `false` are meaningful filters, so only an absent value skips this.
            ->when($active !== null && $active !== '', fn (Builder $query) => $query
                ->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN)))
            ->when($scopeFilter === 'global', fn (Builder $query) => $query
                ->whereNull('tenant_id')->whereNull('organization_id'))
            ->when($scopeFilter === 'tenant', fn (Builder $query) => $query
                ->whereNotNull('tenant_id')->whereNull('organization_id'))
            ->when($scopeFilter === 'organization', fn (Builder $query) => $query
                ->whereNotNull('organization_id'))
            ->when(filled($tenantFilter), fn (Builder $query) => $query->where('tenant_id', $tenantFilter))
            ->when(filled($organizationFilter), fn (Builder $query) => $query->where('organization_id', $organizationFilter))
            ->orderBy($sort, $order);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function paginate(array $filters, int $perPage, ?CouponScope $scope = null): LengthAwarePaginator
    {
        return $this->search($filters, $scope)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        return Coupon::create($data)
            ->load(['tenant:id,name', 'organization:id,name'])
            ->loadCount('redemptions');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);

        return $coupon->refresh()
            ->load(['tenant:id,name', 'organization:id,name'])
            ->loadCount('redemptions');
    }

    /**
     * Archives the code. Redemptions are kept: they record a discount that was
     * genuinely given, and the order still points at them.
     */
    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    /**
     * Headline counts for the header. `active` is exactly what checkout would
     * accept right now; the rest explain why a code would not apply.
     *
     * @return array<string, int>
     */
    public function statistics(?CouponScope $scope = null): array
    {
        $now = now();
        $base = fn (): Builder => Coupon::query()
            ->when($scope !== null, fn (Builder $query) => $scope->constrain($query));

        return [
            'total' => $base()->count(),
            'active' => $base()
                ->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
                ->count(),
            'scheduled' => $base()
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', $now)
                ->count(),
            'expired' => $base()
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', $now)
                ->count(),
            'exhausted' => $base()
                ->whereNotNull('usage_limit')
                ->whereColumn('used_count', '>=', 'usage_limit')
                ->count(),
            'redemptions' => CouponRedemption::query()
                ->when($scope !== null, fn (Builder $query) => $query->whereHas(
                    'coupon',
                    fn (Builder $coupon) => $scope->constrain($coupon)
                ))
                ->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CouponRedemption>
     */
    public function redemptions(Coupon $coupon, int $perPage): LengthAwarePaginator
    {
        return $coupon->redemptions()
            ->with(['user', 'order'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Tenants and organizations, for the scope pickers in the admin UI.
     *
     * Returned together because a coupon form needs both at once, and the set
     * of tenants and organizations is small — a picker does not paginate.
     * Organizations carry `tenant_id` so the UI can narrow them once a tenant is
     * chosen without a second request.
     *
     * @return array{tenants: array<int, array<string, mixed>>, organizations: array<int, array<string, mixed>>}
     */
    public function scopeOptions(): array
    {
        return [
            'tenants' => Tenant::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Tenant $tenant) => ['id' => $tenant->id, 'name' => $tenant->name])
                ->all(),
            'organizations' => Organization::query()
                ->orderBy('name')
                ->get(['id', 'name', 'tenant_id'])
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'tenant_id' => $organization->tenant_id,
                ])
                ->all(),
        ];
    }
}
