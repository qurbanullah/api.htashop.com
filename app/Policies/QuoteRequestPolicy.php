<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\User;

class QuoteRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->activeMemberships()->exists();
    }

    /**
     * Merchants see quotes for their organizations; tenant-level quotes fall
     * back to tenant membership. Admins see everything.
     */
    public function view(User $user, QuoteRequest $quote): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($quote->organization_id !== null
            && $user->hasActiveMembershipInOrganization((int) $quote->organization_id)) {
            return true;
        }

        return $quote->tenant_id !== null
            && $user->hasActiveMembershipInTenant((int) $quote->tenant_id);
    }

    public function update(User $user, QuoteRequest $quote): bool
    {
        return $this->view($user, $quote);
    }

    public function reply(User $user, QuoteRequest $quote): bool
    {
        return $this->view($user, $quote);
    }

    public function assign(User $user, QuoteRequest $quote): bool
    {
        return $this->view($user, $quote);
    }

    public function delete(User $user, QuoteRequest $quote): bool
    {
        return $user->isAdmin();
    }
}
