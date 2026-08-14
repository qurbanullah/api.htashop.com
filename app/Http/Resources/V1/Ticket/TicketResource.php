<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Ticket;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'title' => $this->title,
            'slug' => $this->slug,
            'stype' => $this->stype,
            'severity' => $this->severity,
            'reproducibility' => $this->reproducibility,
            'priority' => $this->priority,
            'status' => $this->status,
            'is_visible' => $this->is_visible,
            'is_resolved' => $this->is_resolved,
            'is_archived' => $this->is_archived,
            'is_locked' => $this->is_locked,
            'description' => $this->description,
            'steps_to_reproduce' => $this->steps_to_reproduce,
            'additional_information' => $this->additional_information,
            'resolved_on' => $this->resolved_on,
            'archived_on' => $this->archived_on,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'reporter' => $this->when(
                $this->relationLoaded('reporter')
                    || $this->relationLoaded('reporterWithTrashed')
                    || $this->guest_name
                    || $this->guest_email,
                function () {
                    $reporter = $this->reporter ?? $this->reporterWithTrashed ?? null;
                    return $reporter ? [
                        'id' => $reporter->id,
                        'name' => $reporter->name,
                        'email' => $reporter->email,
                        'avatar' => $reporter->avatar_small ?? $reporter->avatar_url ?? null,
                        'avatar_urls' => $reporter->getAvatarUrls(),
                    ] : [
                        'id' => null,
                        'name' => $this->guest_name ?? 'Guest',
                        'email' => $this->guest_email,
                        'avatar' => null,
                        'avatar_urls' => null,
                    ];
                }
            ),

            'assigned_user' => $this->when($this->assignedUser(), function () {
                $assignedUser = $this->assignedUser();
                return $assignedUser ? [
                    'id' => $assignedUser->id,
                    'name' => $assignedUser->name,
                    'email' => $assignedUser->email,
                    'avatar' => $assignedUser->avatar_small ?? $assignedUser->avatar_url ?? null,
                    'avatar_urls' => $assignedUser->getAvatarUrls(),
                ] : null;
            }),

            'current_assignment' => $this->when($this->currentAssignment(), function () {
                $assignment = $this->currentAssignment();
                return $assignment ? [
                    'id' => $assignment->id,
                    'assigned_at' => $assignment->assigned_at?->toISOString(),
                    'notes' => $assignment->notes,
                ] : null;
            }),

            'softwares' => $this->whenLoaded('softwares', function () {
                return $this->softwares->map(fn($software) => [
                    'id' => $software->id,
                    'name' => $software->name,
                ]);
            }),

            'versions' => $this->whenLoaded('versions', function () {
                return $this->versions->map(fn($version) => [
                    'id' => $version->id,
                    'name' => $version->name,
                    'version_number' => $version->version_number,
                ]);
            }),

            'ltypes' => $this->whenLoaded('ltypes', function () {
                return $this->ltypes->map(fn($ltype) => [
                    'id' => $ltype->id,
                    'name' => $ltype->name,
                ]);
            }),

            'packages' => $this->whenLoaded('packages', function () {
                return $this->packages->map(fn($package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                ]);
            }),

            'messages_count' => $this->when(
                $this->relationLoaded('messages'),
                fn() => $this->messages->count()
            ),

            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'message' => $m->message,
                        'author' => $m->commenter ? [
                            'id' => $m->commenter->id,
                            'name' => $m->commenter->name,
                            'email' => $m->commenter->email,
                            'avatar' => $m->commenter->avatar_small ?? $m->commenter->avatar_url ?? null,
                            'avatar_urls' => $m->commenter->getAvatarUrls(),
                        ] : null,
                        'created_at' => $m->created_at?->toISOString(),
                        'updated_at' => $m->updated_at?->toISOString(),
                    ];
                });
            }),
        ];
    }
}
