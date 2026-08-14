<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Warehouse $warehouse): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Warehouse $warehouse): bool { return true; }
    public function delete(User $user, Warehouse $warehouse): bool { return true; }
}
