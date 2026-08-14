<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Audit\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    /**
     * Exclude timestamps from auditing as they're auto-managed
     */
    public function getAuditExclude(): array
    {
        return [
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get custom audit description
     */
    public function getAuditDescription(string $event): ?string
    {
        return match ($event) {
            'created' => "Role '{$this->name}' was created",
            'updated' => "Role '{$this->name}' was updated",
            'deleted' => "Role '{$this->name}' was deleted",
            default => null,
        };
    }
}
