<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Audit\Auditable;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
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
            'created' => "Permission '{$this->name}' was created",
            'updated' => "Permission '{$this->name}' was updated",
            'deleted' => "Permission '{$this->name}' was deleted",
            default => null,
        };
    }
}
