<?php

namespace App\Http\Resources\V1\Eula;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EulaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'version' => $this->version,
            'software_id' => $this->software_id,
            'version_id' => $this->version_id,
            'software' => $this->when($this->relationLoaded('software') && $this->software instanceof \App\Models\Software, function () {
                return [
                    'id' => $this->software->id,
                    'name' => $this->software->name,
                ];
            }),
            'software_version' => $this->when($this->relationLoaded('version'), function () {
                $version = $this->getRelationValue('version');
                if ($version instanceof \App\Models\Version) {
                    return [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                    ];
                }

                return null;
            }),
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'consents_count' => $this->when(
                isset($this->consents_count),
                $this->consents_count
            ),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updater' => $this->whenLoaded('updater', function () {
                return $this->updater ? [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ] : null;
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->when(
                $this->deleted_at,
                $this->deleted_at?->toISOString()
            ),
        ];
    }
}
