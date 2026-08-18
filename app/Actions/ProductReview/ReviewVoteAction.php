<?php

namespace App\Actions\ProductReview;

use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;

class ReviewVoteAction
{
    public function handle(string $uuid, User $user, bool $helpful): array
    {
        $review = Review::query()->where('uuid', $uuid)->firstOrFail();

        $vote = ReviewVote::query()
            ->where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();

        if ($vote && $vote->helpful === $helpful) {
            $vote->delete();
            $this->adjust($review, $helpful, -1);
        } elseif ($vote) {
            $this->adjust($review, $vote->helpful, -1);
            $vote->update(['helpful' => $helpful]);
            $this->adjust($review, $helpful, 1);
        } else {
            ReviewVote::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'helpful' => $helpful,
            ]);
            $this->adjust($review, $helpful, 1);
        }

        $review->refresh();

        return [
            'helpful_count' => $review->helpful_count,
            'not_helpful_count' => $review->not_helpful_count,
        ];
    }

    private function adjust(Review $review, bool $helpful, int $delta): void
    {
        if ($helpful) {
            $review->increment('helpful_count', $delta);
        } else {
            $review->increment('not_helpful_count', $delta);
        }
    }
}
