<?php

namespace App\Http\Resources\V1\Feedback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
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
            'type' => $this->type,
            'type_display' => $this->getTypeDisplay(),
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'software_name' => $this->software_name,
            'software_version' => $this->software_version,
            'operating_system' => $this->operating_system,
            'source' => $this->source,
            'admin_response' => $this->admin_response,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'replied_by' => $this->whenLoaded('repliedBy', function () {
                return [
                    'id' => $this->repliedBy->id,
                    'name' => $this->repliedBy->name,
                    'email' => $this->repliedBy->email,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Get type display name
     */
    private function getTypeDisplay(): string
    {
        return match($this->type) {
            'feedback' => 'General Feedback',
            'feature_request' => 'Feature Request',
            'suggestion' => 'Suggestion',
            'bug_report' => 'Bug Report',
            default => ucfirst($this->type),
        };
    }
}
