<?php

namespace App\Services\Messages;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

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

        return ContactMessage::create($data);
    }

    /**
     * Get all contact messages with pagination
     */
    public function getAll(int $perPage = 15)
    {
        return ContactMessage::orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get messages by status
     */
    public function getByStatus(string $status, int $perPage = 15)
    {
        return ContactMessage::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get unread messages count
     */
    public function getUnreadCount(): int
    {
        return ContactMessage::where('status', 'new')->count();
    }

    /**
     * Mark message as read
     */
    public function markAsRead($message): void
    {
        if (is_numeric($message)) {
            $message = ContactMessage::findOrFail($message);
        }

        $message->markAsRead();
    }

    /**
     * Mark message as replied
     */
    public function markAsReplied($message): void
    {
        if (is_numeric($message)) {
            $message = ContactMessage::findOrFail($message);
        }

        $message->markAsReplied();
    }

    /**
     * Delete a contact message
     */
    public function delete($message): bool
    {
        if (is_numeric($message)) {
            $message = ContactMessage::findOrFail($message);
        }

        return $message->delete();
    }

    /**
     * Search messages
     */
    public function search(string $query = null, string $status = null)
    {
        $builder = ContactMessage::query();

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
     * Get statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => ContactMessage::count(),
            'new' => ContactMessage::where('status', 'new')->count(),
            'read' => ContactMessage::where('status', 'read')->count(),
            'replied' => ContactMessage::where('status', 'replied')->count(),
        ];
    }
}
