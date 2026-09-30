<?php

namespace App\Rules;

use App\Models\Coupon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A coupon code must be unique *within its scope*, not globally.
 *
 * Two merchants may both run `EID10`; a platform-wide `SAVE10` is a different
 * coupon from a tenant's `SAVE10`. The database enforces the same rule through
 * the `scope_code` unique index — this rule exists to turn a would-be 500 into a
 * field-level validation error the admin UI can show.
 *
 * NULL scopes are compared with `IS NULL`, not `= NULL`, or every global coupon
 * would validate as unique.
 */
class UniqueCouponCode implements ValidationRule
{
    public function __construct(
        private readonly ?int $tenantId,
        private readonly ?int $organizationId,
        private readonly ?int $ignoreId = null,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalised = Coupon::normaliseCode(is_string($value) ? $value : (string) $value);

        $exists = Coupon::query()
            ->where('code', $normalised)
            ->when(
                $this->tenantId === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $this->tenantId),
            )
            ->when(
                $this->organizationId === null,
                fn ($query) => $query->whereNull('organization_id'),
                fn ($query) => $query->where('organization_id', $this->organizationId),
            )
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The code has already been taken in this scope.');
        }
    }
}
