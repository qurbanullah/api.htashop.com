<?php

namespace App\Http\Resources\V1\Product;

use App\Enums\ProductImportKind;
use App\Enums\ProductImportStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Progress of one staged import. Counts are additive: `total_rows` is what the
 * file contained, the rest describe what has been staged or applied so far.
 */
class ProductImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'original_filename' => $this->original_filename,
            'format' => $this->format,
            'kind' => $this->kind ?? 'products',
            'kind_label' => ProductImportKind::label((string) ($this->kind ?? 'products')),
            'size_bytes' => (int) $this->size_bytes,
            'status' => $this->status,
            'status_label' => ProductImportStatus::label((string) $this->status),
            'is_finished' => ProductImportStatus::isFinished((string) $this->status),
            'progress' => $this->progressPercentage(),
            'options' => (object) ($this->options ?? []),
            'counts' => [
                'total' => (int) $this->total_rows,
                'processed' => (int) $this->processed_rows,
                'created' => (int) $this->created_count,
                'updated' => (int) $this->updated_count,
                'skipped' => (int) $this->skipped_count,
                'failed' => (int) $this->failed_count,
                'valid' => (int) ($this->valid_rows_count ?? 0),
                'invalid' => (int) ($this->invalid_rows_count ?? 0),
                'imported' => (int) ($this->imported_rows_count ?? 0),
            ],
            'error_message' => $this->error_message,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'rows' => ProductImportRowResource::collection($this->whenLoaded('rows')),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
