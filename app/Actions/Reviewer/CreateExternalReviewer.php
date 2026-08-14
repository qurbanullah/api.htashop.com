<?php

namespace App\Actions\Reviewer;

use App\Models\User;
use App\Models\Manuscript;
use App\Models\Reviewer;
use App\Mail\ReviewerInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateExternalReviewer
{
    /**
     * Create a new external reviewer and assign to manuscript
     *
     * @param Manuscript $manuscript
     * @param array $data
     * @param User $createdBy
     * @return Reviewer
     * @throws \Exception
     */
    public function handle(Manuscript $manuscript, array $data, User $createdBy): Reviewer
    {
        // Check user has permission
        if (!$createdBy->hasRole(['editor-in-chief', 'editor', 'admin'])) {
            throw new \Exception('Only editors can create external reviewers');
        }

        // Check email is not already used
        if (User::where('email', $data['email'])->exists()) {
            throw new \Exception('A user with this email already exists. Please use the existing user instead.');
        }

        // Check email is not from manuscript authors
        $authorEmails = $manuscript->authors()
            ->with('user')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->toArray();

        if (in_array(strtolower($data['email']), array_map('strtolower', $authorEmails))) {
            throw new \Exception('This email belongs to one of the manuscript authors. Authors cannot be assigned as reviewers.');
        }

        // Also check corresponding author
        if ($manuscript->correspondingAuthor?->email &&
            strtolower($manuscript->correspondingAuthor->email) === strtolower($data['email'])) {
            throw new \Exception('This email belongs to the corresponding author. Authors cannot be assigned as reviewers.');
        }

        return DB::transaction(function () use ($manuscript, $data, $createdBy, $authorEmails) {
            // Create new user account
            $user = User::create([
                'name' => trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']),
                'email' => $data['email'],
                'password' => bcrypt(Str::random(32)), // Random temporary password
                'email_verified_at' => now(), // Auto-verify email for external reviewers
            ]);

            // Assign reviewer role
            $user->assignRole('reviewer');

            // Create academic profile for the reviewer
            $user->academicProfile()->create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'title' => $data['title'] ?? null,
                'institution' => $data['affiliation'],
                'department' => null,
                'bio' => null,
                'research_areas' => $data['expertise_areas'] ?? [],
                'review_expertise' => $data['expertise_areas'] ?? [],
                'expertise_keywords' => [],
                'available_for_review' => true,
                'reviewer_availability_status' => 'available',
                'max_active_reviews' => 5,
                'is_active' => true,
            ]);

            // Create reviewer assignment
            $reviewer = Reviewer::create([
                'reviewable_type' => 'App\\Models\\Manuscript',
                'reviewable_id' => $manuscript->id,
                'user_id' => $user->id,
                'assigned_by_user_id' => $createdBy->id,
                'status' => 'pending',
                'due_date' => $data['due_date'] ?? now()->addDays(30),
                'invitation_message' => $data['invitation_message'] ?? null,
            ]);

            // Generate password reset token
            $token = Password::createToken($user);
            $resetUrl = route('password.reset.track', ['token' => $token, 'email' => $user->email]);

            // Send invitation email (queued for better performance)
            try {
                Mail::queue(new ReviewerInvitation(
                    reviewer: $user,
                    manuscript: $manuscript,
                    resetPasswordUrl: $resetUrl,
                    dueDateFormatted: $data['due_date']
                        ? \Carbon\Carbon::parse($data['due_date'])->format('F d, Y')
                        : 'To be determined'
                ));
            } catch (\Exception $mailException) {
                Log::warning('Failed to queue reviewer invitation email', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage(),
                ]);
                // Don't fail the entire operation if email fails
            }

            return $reviewer;
        });
    }
}
