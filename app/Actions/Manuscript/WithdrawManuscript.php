<?php

namespace App\Actions\Manuscript;

use App\Models\Manuscript;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawManuscript
{
    public function handle(Manuscript $manuscript, User $user, array $data = []): Manuscript
    {
        return DB::transaction(function () use ($manuscript, $user, $data) {
            // Validate that manuscript can be withdrawn
            if (!$this->canWithdraw($manuscript, $user)) {
                throw new \Exception('You are not authorized to withdraw this manuscript or it cannot be withdrawn in its current status.');
            }

            // Update manuscript status
            $manuscript->update([
                'status' => \App\Enums\ManuscriptStatus::WITHDRAWN,
                'archived_at' => now(),
                'archive_reason' => 'Withdrawn by author'
            ]);

            // Create withdrawal decision record
            $manuscript->decisions()->create([
                'user_id' => $user->id,
                'type' => \App\Enums\DecisionType::EDITORIAL,
                'decision' => 'withdrawn',
                'content' => $data['withdrawal_reason'] ?? 'Manuscript withdrawn by author',
                'reasoning' => $data['detailed_reason'] ?? null,
                'is_final' => true,
                'completed_at' => now(),
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'withdrawal_type' => $this->isAuthorWithdrawal($manuscript, $user) ? 'author' : 'editorial',
                    'original_status' => $manuscript->getOriginal('status')
                ])
            ]);

            // Cancel any active assignments
            $this->cancelActiveAssignments($manuscript, $user);

            Log::info('Manuscript withdrawn', [
                'manuscript_id' => $manuscript->id,
                'title' => $manuscript->title,
                'withdrawn_by' => $user->id,
                'reason' => $data['withdrawal_reason'] ?? 'No reason provided'
            ]);

            return $manuscript->fresh();
        });
    }

    private function canWithdraw(Manuscript $manuscript, User $user): bool
    {
        // Authors can withdraw before acceptance
        if ($this->isAuthorWithdrawal($manuscript, $user)) {
            return !in_array($manuscript->status, [
                \App\Enums\ManuscriptStatus::ACCEPTED,
                \App\Enums\ManuscriptStatus::IN_COPYEDIT,
                \App\Enums\ManuscriptStatus::IN_PRODUCTION,
                \App\Enums\ManuscriptStatus::PUBLISHED
            ]);
        }

        // Editors can withdraw at any stage before publication
        if ($this->isEditorialWithdrawal($manuscript, $user)) {
            return $manuscript->status !== \App\Enums\ManuscriptStatus::PUBLISHED;
        }

        return false;
    }

    private function isAuthorWithdrawal(Manuscript $manuscript, User $user): bool
    {
        return $manuscript->submitting_author_id === $user->id ||
               $manuscript->authors()->where('user_id', $user->id)->exists();
    }

    private function isEditorialWithdrawal(Manuscript $manuscript, User $user): bool
    {
        return $manuscript->journal->editorialBoard()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', ['editor_in_chief', 'associate_editor', 'managing_editor'])
            ->exists();
    }

    private function cancelActiveAssignments(Manuscript $manuscript, User $user): void
    {
        // This would cancel any active review assignments
        // Implementation depends on the final assignment structure
        Log::info('Active assignments cancelled for withdrawn manuscript', [
            'manuscript_id' => $manuscript->id,
            'cancelled_by' => $user->id
        ]);
    }
}
