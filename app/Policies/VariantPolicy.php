<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Variant;

class VariantPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Variant $variant): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Variant $variant): bool { return true; }
    public function delete(User $user, Variant $variant): bool { return true; }
}
