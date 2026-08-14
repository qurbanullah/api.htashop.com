<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for storing minimal information about permanently deleted users
 * for GDPR compliance and preventing null reference errors
 */
class DeletedUser extends Model
{
        use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'original_user_id',
        'deleted_by',
        'deleted_by_type',
        'deleted_by_name',
        'deleted_by_email',
        'name',
        'email',
        'soft_deleted_at',
        'permanently_deleted_at',
        'deletion_reason',
        'anonymized_identifier',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'soft_deleted_at' => 'datetime',
            'permanently_deleted_at' => 'datetime',
            'deletion_reason' => 'json',
        ];
    }

    /**
     * Get the display name for the deleted user
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->anonymized_identifier ?? $this->name ?? "[Permanently Deleted User #{$this->original_user_id}]";
    }

    /**
     * Get safe email for the deleted user
     */
    public function getSafeEmailAttribute(): string
    {
        return $this->email ?? '[permanently-deleted@gdpr-compliance.local]';
    }

    /**
     * Create a deleted user record from an existing user
     */
    public static function createFromUser(User $user, array $deletionReason = [], ?User $deletedBy = null): self
    {
        $deletedByInfo = self::getDeletedByInfo($deletedBy);

        return self::create([
            'original_user_id' => $user->id,
            'deleted_by' => $deletedByInfo['id'],
            'deleted_by_type' => $deletedByInfo['type'],
            'deleted_by_name' => $deletedByInfo['name'],
            'deleted_by_email' => $deletedByInfo['email'],
            'name' => $user->name,
            'email' => $user->email,
            'soft_deleted_at' => $user->deleted_at,
            'permanently_deleted_at' => now(),
            'deletion_reason' => $deletionReason,
            'anonymized_identifier' => "User #{$user->id}",
        ]);
    }

    /**
     * Create an anonymized deleted user record
     */
    public static function createAnonymized(int $originalUserId, array $deletionReason = [], ?User $deletedBy = null): self
    {
        $deletedByInfo = self::getDeletedByInfo($deletedBy);

        return self::create([
            'original_user_id' => $originalUserId,
            'deleted_by' => $deletedByInfo['id'],
            'deleted_by_type' => $deletedByInfo['type'],
            'deleted_by_name' => $deletedByInfo['name'],
            'deleted_by_email' => $deletedByInfo['email'],
            'name' => null, // Anonymized
            'email' => null, // Anonymized
            'soft_deleted_at' => null,
            'permanently_deleted_at' => now(),
            'deletion_reason' => $deletionReason,
            'anonymized_identifier' => "User #{$originalUserId}",
        ]);
    }

    /**
     * Get information about who deleted the user
     */
    private static function getDeletedByInfo(?User $deletedBy): array
    {
        if (!$deletedBy) {
            return [
                'id' => null,
                'type' => 'system',
                'name' => 'System (Automated)',
                'email' => null,
            ];
        }

        // Determine the type based on user role or attributes
        $type = 'user';
        if (method_exists($deletedBy, 'hasRole')) {
            if ($deletedBy->hasRole('superadmin')) {
                $type = 'superadmin';
            } elseif ($deletedBy->hasRole('admin')) {
                $type = 'admin';
            }
        } elseif (isset($deletedBy->role)) {
            $type = $deletedBy->role;
        }

        return [
            'id' => $deletedBy->id,
            'type' => $type,
            'name' => $deletedBy->name,
            'email' => $deletedBy->email,
        ];
    }

    /**
     * Get information about who deleted this user
     */
    public function getDeletedByInfoAttribute(): string
    {
        if ($this->deleted_by_type === 'system') {
            return 'System (Automated GDPR Cleanup)';
        }

        $name = $this->deleted_by_name ?? 'Unknown';
        $type = ucfirst($this->deleted_by_type);

        if ($this->deleted_by_email) {
            return "{$name} ({$type} - {$this->deleted_by_email})";
        }

        return "{$name} ({$type})";
    }
}
