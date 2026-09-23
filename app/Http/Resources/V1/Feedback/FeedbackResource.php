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
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'type_display' => $this->type_display,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'source' => $this->source,
            'page_url' => $this->page_url,
            'additional_info' => $this->additional_info ?? [],
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
}
