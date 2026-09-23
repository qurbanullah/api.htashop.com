<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\ConversationStatusEnum;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Ai\ChatIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Resolves the conversation behind the current visitor.
 *
 * The thread is keyed by a hash of the visitor's opaque cookie token. The client
 * never sends or receives a conversation id, so one visitor cannot read
 * another's transcript.
 */
class ConversationService
{
    public function resolve(Request $request, ?User $user, string $locale): Conversation
    {
        $existing = $this->current($request);

        if ($existing) {
            $this->sync($existing, $user, $locale);

            return $existing;
        }

        return Conversation::create([
            'visitor_key' => ChatIdentity::visitorKey($request),
            'user_id' => $user?->id,
            'locale' => $locale,
            'status' => ConversationStatusEnum::OPEN->value,
        ]);
    }

    /**
     * The visitor's most recent conversation that is not closed.
     */
    public function current(Request $request): ?Conversation
    {
        return Conversation::query()
            ->where('visitor_key', ChatIdentity::visitorKey($request))
            ->where('status', '!=', ConversationStatusEnum::CLOSED->value)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Abandon any open thread; the next message starts a new one.
     */
    public function reset(Request $request): void
    {
        Conversation::query()
            ->where('visitor_key', ChatIdentity::visitorKey($request))
            ->where('status', '!=', ConversationStatusEnum::CLOSED->value)
            ->update(['status' => ConversationStatusEnum::CLOSED->value]);
    }

    protected function sync(Conversation $conversation, ?User $user, string $locale): void
    {
        $dirty = false;

        if ($user && $conversation->user_id !== $user->id) {
            $conversation->user_id = $user->id;
            $dirty = true;
        }

        if ($locale !== '' && $conversation->locale !== $locale) {
            $conversation->locale = $locale;
            $dirty = true;
        }

        if ($dirty) {
            $conversation->save();
        }
    }

    /**
     * Admin listing query.
     *
     * @param  array{search?: ?string, status?: ?string, needs_attention?: ?string}  $filters
     */
    public function search(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Conversation::query()
            ->with('user')
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when(
                ($filters['needs_attention'] ?? null) !== null && $filters['needs_attention'] !== '',
                fn (Builder $query) => $query->where('needs_attention', filter_var($filters['needs_attention'], FILTER_VALIDATE_BOOLEAN))
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('uuid', 'like', $term)
                        ->orWhereHas('user', function (Builder $user) use ($term): void {
                            $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    /**
     * Counts and signals for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function statistics(): array
    {
        $statuses = Conversation::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return [
            'total' => array_sum($statuses),
            'open' => (int) ($statuses[ConversationStatusEnum::OPEN->value] ?? 0),
            'escalated' => (int) ($statuses[ConversationStatusEnum::ESCALATED->value] ?? 0),
            'closed' => (int) ($statuses[ConversationStatusEnum::CLOSED->value] ?? 0),
            'needs_attention' => Conversation::query()->where('needs_attention', true)->count(),
            'messages' => ChatMessage::query()->count(),
            'helpful' => ChatMessage::query()->where('feedback', 'helpful')->count(),
            'unhelpful' => ChatMessage::query()->where('feedback', 'unhelpful')->count(),
            'gaps' => ChatEvent::query()->where('type', ChatEvent::TYPE_GAP_DETECTED)->count(),
        ];
    }
}
