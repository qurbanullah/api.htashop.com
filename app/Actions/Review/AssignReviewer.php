<?php

namespace App\Actions\Review;

use App\Models\Manuscript;
use App\Models\User;
use App\Models\Assignment;
use App\Enums\AssignmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignReviewer
{
    public function handle(Manuscript $manuscript, User $reviewer, User $assignedBy, array $data = []): Assignment
    {
        return DB::transaction(function () use ($manuscript, $reviewer, $assignedBy, $data) {
            // Validate reviewer can be assigned
            $this->validateReviewerAssignment($manuscript, $reviewer, $assignedBy);

            // Create the assignment (adapting to current assignment table structure)
            $assignment = Assignment::create([
                'submission_id' => $manuscript->id, // Using existing structure
                'assignee_id' => $reviewer->id,
                'assigned_by_id' => $assignedBy->id,
                'type' => 'review',
                'status' => 'pending',
                'instructions' => $data['instructions'] ?? $this->getDefaultInstructions($manuscript),
                'assigned_at' => now(),
                'due_date' => $data['due_date'] ?? now()->addWeeks(3),
                'review_criteria' => $data['review_criteria'] ?? $this->getDefaultCriteria(),
                'round' => $data['round'] ?? 1,
                'is_confidential' => $data['is_confidential'] ?? true,
                'notes' => $data['notes'] ?? null
            ]);

            // Create corresponding decision record
            $manuscript->decisions()->create([
                'user_id' => $assignedBy->id,
                'type' => \App\Enums\DecisionType::EDITORIAL,
                'decision' => 'reviewer_assigned',
                'content' => "Reviewer assigned: {$reviewer->name}",
                'completed_at' => now(),
                'metadata' => [
                    'reviewer_id' => $reviewer->id,
                    'assignment_id' => $assignment->id,
                    'due_date' => $assignment->due_date->toISOString()
                ]
            ]);

            Log::info('Reviewer assigned to manuscript', [
                'manuscript_id' => $manuscript->id,
                'reviewer_id' => $reviewer->id,
                'assigned_by' => $assignedBy->id,
                'assignment_id' => $assignment->id,
                'due_date' => $assignment->due_date
            ]);

            return $assignment;
        });
    }

    private function validateReviewerAssignment(Manuscript $manuscript, User $reviewer, User $assignedBy): void
    {
        // Check if assignor has permission
        if (!$this->canAssignReviewers($manuscript, $assignedBy)) {
            throw new \Exception('You do not have permission to assign reviewers for this manuscript.');
        }

        // Check if reviewer is not the author
        if ($manuscript->submitting_author_id === $reviewer->id) {
            throw new \Exception('Cannot assign the submitting author as a reviewer.');
        }

        // Check if reviewer is not already assigned
        $existingAssignment = Assignment::where('submission_id', $manuscript->id)
            ->where('assignee_id', $reviewer->id)
            ->where('type', 'review')
            ->where('status', '!=', 'completed')
            ->first();

        if ($existingAssignment) {
            throw new \Exception('This reviewer is already assigned to this manuscript.');
        }

        // Check if reviewer is in excluded list
        $excludedReviewers = $manuscript->excluded_reviewers ?? [];
        if (in_array($reviewer->id, $excludedReviewers)) {
            throw new \Exception('This reviewer is in the manuscript\'s excluded reviewers list.');
        }
    }

    private function canAssignReviewers(Manuscript $manuscript, User $user): bool
    {
        return $manuscript->journal->editorialBoard()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', ['editor_in_chief', 'associate_editor', 'managing_editor'])
            ->exists();
    }

    private function getDefaultInstructions(Manuscript $manuscript): string
    {
        return "Please review this manuscript titled '{$manuscript->title}' according to the journal's review criteria. " .
               "Provide detailed feedback on the scientific merit, methodology, clarity, and significance of the work. " .
               "Your review should be constructive and help improve the manuscript quality.";
    }

    private function getDefaultCriteria(): array
    {
        return [
            'scientific_merit' => 'Assess the novelty and significance of the research',
            'methodology' => 'Evaluate the appropriateness and rigor of the methods used',
            'clarity' => 'Comment on the clarity and organization of the manuscript',
            'technical_quality' => 'Review the technical accuracy and completeness',
            'ethical_considerations' => 'Ensure ethical standards are met',
            'references' => 'Check if references are appropriate and complete'
        ];
    }
}
