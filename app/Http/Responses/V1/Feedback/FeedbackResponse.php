<?php

namespace App\Http\Responses\V1\Feedback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_display' => $this->type_display,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'software_info' => $this->when($this->software_name || $this->software_version || $this->operating_system, [
                'name' => $this->software_name,
                'version' => $this->software_version,
                'operating_system' => $this->operating_system,
            ]),
            'additional_info' => $this->additional_info,
            'source' => $this->source,
            'admin_response' => $this->when($this->admin_response, $this->admin_response),
            'replied_at' => $this->when($this->replied_at, $this->replied_at->toISOString()),
            'replied_by' => $this->when($this->repliedBy, [
                'id' => $this->repliedBy->id,
                'name' => $this->repliedBy->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'feedback_types' => [
                    'feedback' => 'General Feedback',
                    'feature_request' => 'Feature Request',
                    'suggestion' => 'Suggestion',
                    'bug_report' => 'Bug Report',
                ],
                'status_types' => [
                    'new' => 'New',
                    'read' => 'Read',
                    'replied' => 'Replied',
                    'closed' => 'Closed',
                ],
                'priority_levels' => [
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ],
            ],
        ];
    }
}
