<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

use App\Models\User;
use App\Models\DeletedUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling GDPR compliance related to user data deletion
 */
class GdprService
{
    /**
     * Permanently delete a user after GDPR retention period
     * This method handles the complete process of permanent deletion
     *
     * @param User $user The user to permanently delete
     * @param array $deletionReason Reason for deletion
     * @param bool $anonymize Whether to anonymize user data
     * @param User|null $deletedBy Who is deleting the user (null for system)
     * @return DeletedUser
     * @throws \Exception
     */
    public function permanentlyDeleteUser(User $user, array $deletionReason = [], bool $anonymize = true, ?User $deletedBy = null): DeletedUser
    {
        return DB::transaction(function () use ($user, $deletionReason, $anonymize, $deletedBy) {
            // Log the deletion for audit purposes
            Log::info("Starting permanent deletion of user", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'anonymize' => $anonymize,
                'reason' => $deletionReason,
                'deleted_by_id' => $deletedBy?->id,
                'deleted_by_type' => $deletedBy ? 'manual' : 'system'
            ]);

            // Create the deleted user record first
            if ($anonymize) {
                $deletedUser = DeletedUser::createAnonymized($user->id, $deletionReason, $deletedBy);
            } else {
                $deletedUser = DeletedUser::createFromUser($user, $deletionReason, $deletedBy);
            }

            // Update related records to handle the permanent deletion
            $this->updateRelatedRecords($user->id);

            // Force delete the user (permanent deletion)
            $user->forceDelete();

            Log::info("Successfully permanently deleted user", [
                'original_user_id' => $deletedUser->original_user_id,
                'anonymized_identifier' => $deletedUser->anonymized_identifier
            ]);

            return $deletedUser;
        });
    }

    /**
     * Batch permanently delete multiple users
     *
     * @param array $userIds Array of user IDs to delete
     * @param array $deletionReason Reason for deletion
     * @param bool $anonymize Whether to anonymize user data
     * @param User|null $deletedBy Who is deleting the users
     * @return array Array of DeletedUser records
     */
    public function batchPermanentlyDeleteUsers(array $userIds, array $deletionReason = [], bool $anonymize = true, ?User $deletedBy = null): array
    {
        $deletedUsers = [];

        foreach ($userIds as $userId) {
            $user = User::withTrashed()->find($userId);
            if ($user) {
                $deletedUsers[] = $this->permanentlyDeleteUser($user, $deletionReason, $anonymize, $deletedBy);
            }
        }

        return $deletedUsers;
    }

    /**
     * Get users eligible for permanent deletion based on GDPR retention period
     *
     * @param int $retentionDays Number of days after soft deletion before permanent deletion (default: 30)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUsersEligibleForPermanentDeletion(int $retentionDays = 30)
    {
        $cutoffDate = now()->subDays($retentionDays);

        return User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoffDate)
            ->get();
    }

    /**
     * Automated GDPR cleanup - permanently delete users past retention period
     *
     * @param int $retentionDays Number of days after soft deletion
     * @param array $deletionReason Reason for automated deletion
     * @param bool $anonymize Whether to anonymize data
     * @return array Results of the cleanup
     */
    public function performAutomatedGdprCleanup(int $retentionDays = 30, array $deletionReason = [], bool $anonymize = true): array
    {
        $eligibleUsers = $this->getUsersEligibleForPermanentDeletion($retentionDays);

        $results = [
            'total_eligible' => $eligibleUsers->count(),
            'successfully_deleted' => 0,
            'failed_deletions' => [],
            'deleted_user_records' => []
        ];

        $defaultDeletionReason = array_merge([
            'type' => 'automated_gdpr_cleanup',
            'retention_period_days' => $retentionDays,
            'cleanup_date' => now()->toISOString()
        ], $deletionReason);

        foreach ($eligibleUsers as $user) {
            try {
                $deletedUser = $this->permanentlyDeleteUser($user, $defaultDeletionReason, $anonymize);
                $results['successfully_deleted']++;
                $results['deleted_user_records'][] = $deletedUser;
            } catch (\Exception $e) {
                $results['failed_deletions'][] = [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ];
                Log::error("Failed to permanently delete user during GDPR cleanup", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info("GDPR automated cleanup completed", $results);

        return $results;
    }

    /**
     * Update related records when a user is permanently deleted
     * This method should be extended based on your application's relationships
     *
     * @param int $userId
     * @return void
     */
    protected function updateRelatedRecords(int $userId): void
    {
        // Note: In a real application, you might want to:
        // 1. Update foreign key references to NULL where appropriate
        // 2. Archive or anonymize related data
        // 3. Update any audit logs or activity records

        // For now, we don't need to do anything special since we're using
        // the WithTrashed relationships and safe helper functions

        Log::info("Updated related records for permanently deleted user", [
            'user_id' => $userId
        ]);
    }

    /**
     * Check if a user ID corresponds to a permanently deleted user
     *
     * @param int $userId
     * @return bool
     */
    public function isUserPermanentlyDeleted(int $userId): bool
    {
        return DeletedUser::where('original_user_id', $userId)->exists();
    }

    /**
     * Get information about a permanently deleted user
     *
     * @param int $originalUserId
     * @return DeletedUser|null
     */
    public function getPermanentlyDeletedUserInfo(int $originalUserId): ?DeletedUser
    {
        return DeletedUser::where('original_user_id', $originalUserId)->first();
    }

    /**
     * Generate a report of all permanently deleted users
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public function generateDeletionReport(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $deletedUsers = DeletedUser::where('permanently_deleted_at', '>=', $startDate)
            ->orderBy('permanently_deleted_at', 'desc')
            ->get();

        return [
            'period_days' => $days,
            'total_permanently_deleted' => $deletedUsers->count(),
            'deletion_summary' => $deletedUsers->groupBy(function ($user) {
                return $user->permanently_deleted_at->format('Y-m-d');
            })->map(function ($group) {
                return $group->count();
            }),
            'deletion_reasons' => $deletedUsers->pluck('deletion_reason')->filter()->values(),
            'anonymized_count' => $deletedUsers->whereNull('name')->count(),
            'non_anonymized_count' => $deletedUsers->whereNotNull('name')->count(),
        ];
    }
}
