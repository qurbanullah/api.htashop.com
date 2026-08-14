<?php

namespace App\Http\Resources\V1\Forum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'admin_notes' => $this->admin_notes,
            'reporter' => $this->when(
                $this->relationLoaded('reporter') && $this->reporter,
                fn () => [
                    'id' => $this->reporter->id,
                    'name' => $this->reporter->name,
                    'email' => $this->reporter->email,
                ]
            ),
            'reviewer' => $this->when(
                $this->relationLoaded('reviewer') && $this->reviewer,
                fn () => [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ]
            ),
            'reportable_type' => $this->reportable_type === \App\Models\ForumPost::class ? 'post' : 'comment',
            'reportable_id' => $this->reportable_id,
            'reportable' => $this->when(
                $this->relationLoaded('reportable') && $this->reportable,
                function () {
                    if ($this->reportable instanceof \App\Models\ForumPost) {
                        return [
                            'type' => 'post',
                            'id' => $this->reportable->id,
                            'uuid' => $this->reportable->uuid,
                            'title' => $this->reportable->title,
                            'slug' => $this->reportable->slug,
                            'body_preview' => \Illuminate\Support\Str::limit(strip_tags($this->reportable->body), 200),
                        ];
                    }
                    return [
                        'type' => 'comment',
                        'id' => $this->reportable->id,
                        'uuid' => $this->reportable->uuid,
                        'body_preview' => \Illuminate\Support\Str::limit(strip_tags($this->reportable->body), 200),
                        'post_id' => $this->reportable->post_id,
                    ];
                }
            ),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
