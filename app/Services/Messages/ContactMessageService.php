<?php

namespace App\Services\Messages;

use App\Helpers\CacheHelper;
use App\Models\ContactMessage;
use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;

class ContactMessageService
{
    /**
     * Store a new contact message
     */
    public function store(array $data): ContactMessage
    {
        $existingMetadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : [];

        $data['metadata'] = array_filter([
            ...$existingMetadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'submitted_at' => now()->toISOString(),
        ], static fn ($value) => $value !== null && $value !== '');

        $data['tenant_id'] = $data['tenant_id'] ?? TenantContext::publicTenantId();

        $message = ContactMessage::create($data);

        CacheHelper::clearTags(['support']);

        return $message;
    }

    /**
     * Get all contact messages with pagination (admin scope)
     */
    public function getAll(int $perPage = 15)
    {
        return $this->applyTenantScope(ContactMessage::query())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get messages by status (admin scope)
     */
    public function getByStatus(string $status, int $perPage = 15)
    {
        return $this->applyTenantScope(ContactMessage::query())
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get unread messages count (admin scope)
     */
    public function getUnreadCount(): int
    {
        return $this->applyTenantScope(ContactMessage::query())
            ->where('status', 'new')
            ->count();
    }

    /**
     * Find a message visible to the current admin, or null.
     */
    public function findVisible(int $id): ?ContactMessage
    {
        return $this->applyTenantScope(ContactMessage::query())
            ->where('contact_messages.id', $id)
            ->first();
    }

    /**
     * Mark message as read
     */
    public function markAsRead($message): void
    {
        if (is_numeric($message)) {
            $message = $this->findVisible((int) $message);
            if (!$message) {
                return;
            }
        }

        $message->markAsRead();
        CacheHelper::clearTags(['support']);
    }

    /**
     * Mark message as replied and store the admin response
     */
    public function markAsReplied($message, string $response, int $repliedBy): void
    {
        if (is_numeric($message)) {
            $message = $this->findVisible((int) $message);
            if (!$message) {
                return;
            }
        }

        $message->markAsReplied($response, $repliedBy);
        CacheHelper::clearTags(['support']);
    }

    /**
     * Delete a contact message
     */
    public function delete($message): bool
    {
        if (is_numeric($message)) {
            $message = $this->findVisible((int) $message);
            if (!$message) {
                return false;
            }
        }

        $deleted = $message->delete();

        if ($deleted) {
            CacheHelper::clearTags(['support']);
        }

        return $deleted;
    }

    /**
     * Search messages (admin scope)
     */
    public function search(?string $query = null, ?string $status = null): Builder
    {
        $builder = $this->applyTenantScope(ContactMessage::query());

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('subject', 'LIKE', "%{$query}%")
                  ->orWhere('message', 'LIKE', "%{$query}%");
            });
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder->orderBy('created_at', 'desc');
    }

    /**
     * Get statistics (cached, admin scope aware)
     */
    public function getStatistics(): array
    {
        $scope = TenantContext::adminScope();

        return CacheHelper::remember(
            ['support'],
            $this->statisticsKey($scope),
            60,
            fn () => $this->calculateStatistics($scope),
        );
    }

    /**
     * Calculate the statistics for a given admin scope.
     */
    private function calculateStatistics(?int $scope): array
    {
        return [
            'total' => $this->scopedQuery($scope)->count(),
            'new' => $this->scopedQuery($scope)->where('status', 'new')->count(),
            'read' => $this->scopedQuery($scope)->where('status', 'read')->count(),
            'replied' => $this->scopedQuery($scope)->where('status', 'replied')->count(),
        ];
    }

    /**
     * Apply the current admin tenant scope to a query.
     */
    private function applyTenantScope(Builder $query): Builder
    {
        return $this->scopedQuery(TenantContext::adminScope(), $query);
    }

    /**
     * Build a query limited to the given admin scope.
     *
     * @param  int|null  $scope  null = global, 0 = no rows, > 0 = tenant id
     */
    private function scopedQuery(?int $scope, ?Builder $query = null): Builder
    {
        $query ??= ContactMessage::query();

        if ($scope === 0) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope !== null) {
            return $query->where('contact_messages.tenant_id', $scope);
        }

        return $query;
    }

    /**
     * Cache key for statistics of a given admin scope.
     */
    private function statisticsKey(?int $scope): string
    {
        return $scope === null
            ? 'support:contact:stats:all'
            : 'support:contact:stats:tenant:' . $scope;
    }
}
