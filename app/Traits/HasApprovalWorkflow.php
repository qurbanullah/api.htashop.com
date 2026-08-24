<?php

namespace App\Traits;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pending-approval workflow for global catalog entities (manufacturers,
 * brands). Admin-created entries are approved immediately; vendor-created
 * entries are marked pending and must be reviewed by an admin.
 */
trait HasApprovalWorkflow
{
    /**
     * Apply ownership/provenance for a vendor-created entry, or mark
     * admin-created entries as approved outright.
     */
    public static function applyOrigin(array &$data, ?User $user): void
    {
        $isAdmin = $user && $user->hasRole(['super-admin', 'admin']);
        $membership = $user?->memberships()->where('is_active', true)->first();

        $data['origin'] = $isAdmin ? 'admin' : 'vendor';
        $data['is_approved'] = $isAdmin;

        if ($isAdmin) {
            $data['approved_at'] = now();
        } else {
            $data['approved_at'] = null;
            $data['tenant_id'] = $data['tenant_id'] ?? $membership?->tenant_id;
            $data['organization_id'] = $data['organization_id'] ?? $membership?->organization_id;
        }
    }

    public function approve(?string $note = null): void
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'is_approved' => false,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Scope used by vendor-facing endpoints: approved entries from anyone,
     * plus the vendor's own pending entries.
     */
    public function scopeVendorVisible(Builder $query, ?User $user): Builder
    {
        $tenantId = $user?->memberships()->where('is_active', true)->first()?->tenant_id;

        return $query->where(function (Builder $inner) use ($tenantId) {
            $inner->where('is_approved', true);

            if ($tenantId) {
                $inner->orWhere(function (Builder $own) use ($tenantId) {
                    $own->where('is_approved', false)
                        ->where('origin', 'vendor')
                        ->where('tenant_id', $tenantId);
                });
            }
        });
    }

    public function scopeAdminView(Builder $query, ?string $approvalStatus = null): Builder
    {
        return $query->when($approvalStatus === 'pending', fn (Builder $q) => $q->where('is_approved', false))
            ->when($approvalStatus === 'approved', fn (Builder $q) => $q->where('is_approved', true));
    }
}
