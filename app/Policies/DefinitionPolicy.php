<?php

namespace App\Policies;

use App\Models\Definition;
use App\Models\User;

class DefinitionPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Definition $definition): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Definition $definition): bool { return true; }
    public function delete(User $user, Definition $definition): bool { return true; }
}
