<?php

namespace App\Http\Resources\V1\Contact;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessageResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'order_uuid' => $this->order_uuid,
            'subject' => $this->subject,
            'message' => $this->message,
            'admin_response' => $this->admin_response,
            'status' => $this->status,
            'read_at' => $this->read_at?->toIso8601String(),
            'replied_at' => $this->replied_at?->toIso8601String(),
            'replied_by' => $this->whenLoaded('repliedBy', function () {
                return [
                    'id' => $this->repliedBy->id,
                    'name' => $this->repliedBy->name,
                    'email' => $this->repliedBy->email,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
