<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Email;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Email Log Resource
 *
 * Transform email log data for API responses
 */
class EmailLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'mailable_type' => class_basename($this->mailable_type),
            'mailable_type_full' => $this->mailable_type,
            'recipient_email' => $this->recipient_email,
            'recipient_name' => $this->recipient_name,
            'subject' => $this->subject,
            'status' => $this->status,
            'status_color' => $this->status_color,
            'error_message' => $this->error_message,
            'error_details' => $this->error_details,
            'context' => [
                'type' => $this->context_type,
                'id' => $this->context_id,
                'data' => $this->context_data,
            ],
            'user' => $this->when($this->user, [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'triggered_by' => $this->when($this->triggeredBy, [
                'id' => $this->triggeredBy?->id,
                'name' => $this->triggeredBy?->name,
                'email' => $this->triggeredBy?->email,
            ]),
            'metadata' => $this->metadata,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'retry_count' => $this->retry_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
