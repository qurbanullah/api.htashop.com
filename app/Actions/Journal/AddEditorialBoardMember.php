<?php

namespace App\Actions\Journal;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddEditorialBoardMember
{
    public function handle(Journal $journal, array $data, User $currentUser): void
    {
        DB::transaction(function () use ($journal, $data, $currentUser) {
            // Check if user is already on the editorial board
            $existingMember = $journal->editorialBoard()
                ->where('user_id', $data['user_id'])
                ->first();

            if ($existingMember) {
                // Update existing member
                $existingMember->update([
                    'role' => $data['role'],
                    'is_active' => $data['is_active'] ?? true,
                    'bio' => $data['bio'] ?? $existingMember->bio,
                    'expertise' => $data['expertise'] ?? $existingMember->expertise,
                    'metadata' => array_merge($existingMember->metadata ?? [], $data['metadata'] ?? [])
                ]);

                Log::info('Editorial board member updated', [
                    'journal_id' => $journal->id,
                    'user_id' => $data['user_id'],
                    'role' => $data['role'],
                    'updated_by' => $currentUser->id
                ]);
            } else {
                // Create new member
                $journal->editorialBoard()->create([
                    'user_id' => $data['user_id'],
                    'role' => $data['role'],
                    'is_active' => $data['is_active'] ?? true,
                    'joined_at' => now(),
                    'bio' => $data['bio'] ?? null,
                    'expertise' => $data['expertise'] ?? [],
                    'metadata' => $data['metadata'] ?? []
                ]);

                Log::info('Editorial board member added', [
                    'journal_id' => $journal->id,
                    'user_id' => $data['user_id'],
                    'role' => $data['role'],
                    'added_by' => $currentUser->id
                ]);
            }
        });
    }
}
