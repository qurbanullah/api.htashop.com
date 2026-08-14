<?php

namespace App\Actions\Manuscript;

use App\Models\Manuscript;
use App\Models\User;
use App\Enums\ManuscriptStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateManuscriptStatus
{
    public function handle(Manuscript $manuscript, ManuscriptStatus $newStatus, User $user, array $data = []): Manuscript
    {
        return DB::transaction(function () use ($manuscript, $newStatus, $user, $data) {
            $oldStatus = $manuscript->status;

            // Validate status transition
            if (!$oldStatus->canTransitionTo($newStatus)) {
                throw new \Exception("Cannot transition from {$oldStatus->label()} to {$newStatus->label()}");
            }

            // Update manuscript status
            $manuscript->update([
                'status' => $newStatus,
                'submission_date' => $newStatus === ManuscriptStatus::SUBMITTED ? now() : $manuscript->submission_date
            ]);

            // Create decision record
            $decisionType = $this->getDecisionType($newStatus);
            $manuscript->decisions()->create([
                'user_id' => $user->id,
                'type' => $decisionType,
                'decision' => $newStatus->value,
                'content' => $data['decision_content'] ?? "Status changed to {$newStatus->label()}",
                'reasoning' => $data['reasoning'] ?? null,
                'recommendation' => $data['recommendation'] ?? null,
                'is_final' => $this->isFinalDecision($newStatus),
                'completed_at' => now(),
                'metadata' => $data['metadata'] ?? []
            ]);

            // Handle specific status changes
            $this->handleStatusSpecificActions($manuscript, $newStatus, $user, $data);

            Log::info('Manuscript status updated', [
                'manuscript_id' => $manuscript->id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'updated_by' => $user->id
            ]);

            return $manuscript->fresh();
        });
    }

    private function getDecisionType(ManuscriptStatus $status): \App\Enums\DecisionType
    {
        return match ($status) {
            ManuscriptStatus::UNDER_REVIEW => \App\Enums\DecisionType::EDITORIAL,
            ManuscriptStatus::REVISION_REQUESTED => \App\Enums\DecisionType::REVISION_REQUEST,
            ManuscriptStatus::ACCEPTED => \App\Enums\DecisionType::FINAL_ACCEPTANCE,
            ManuscriptStatus::REJECTED => \App\Enums\DecisionType::REJECTION,
            ManuscriptStatus::IN_COPYEDIT => \App\Enums\DecisionType::COPYEDIT,
            ManuscriptStatus::IN_PRODUCTION => \App\Enums\DecisionType::PRODUCTION,
            ManuscriptStatus::PUBLISHED => \App\Enums\DecisionType::PUBLICATION,
            default => \App\Enums\DecisionType::EDITORIAL
        };
    }

    private function isFinalDecision(ManuscriptStatus $status): bool
    {
        return in_array($status, [
            ManuscriptStatus::ACCEPTED,
            ManuscriptStatus::REJECTED,
            ManuscriptStatus::PUBLISHED,
            ManuscriptStatus::WITHDRAWN
        ]);
    }

    private function handleStatusSpecificActions(Manuscript $manuscript, ManuscriptStatus $status, User $user, array $data): void
    {
        switch ($status) {
            case ManuscriptStatus::UNDER_REVIEW:
                // Create reviewer assignments if provided
                if (!empty($data['reviewer_ids'])) {
                    $this->assignReviewers($manuscript, $data['reviewer_ids'], $user);
                }
                break;

            case ManuscriptStatus::PUBLISHED:
                // Set publication date and issue if provided
                $manuscript->update([
                    'published_at' => $data['published_at'] ?? now(),
                    'issue_id' => $data['issue_id'] ?? null,
                    'doi' => $data['doi'] ?? null
                ]);
                break;

            case ManuscriptStatus::REJECTED:
            case ManuscriptStatus::WITHDRAWN:
                // Archive the manuscript
                $manuscript->update([
                    'archived_at' => now(),
                    'archive_reason' => $data['archive_reason'] ?? $status->label()
                ]);
                break;
        }
    }

    private function assignReviewers(Manuscript $manuscript, array $reviewerIds, User $assignedBy): void
    {
        foreach ($reviewerIds as $reviewerId) {
            // Create assignment using the existing assignment structure
            // This would need to be adapted based on the actual assignment table structure
        }
    }
}
