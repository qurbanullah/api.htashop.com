<?php

namespace App\Http\Resources\V1\Quote;

use App\Enums\QuoteStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requirements = is_array($this->requirements) ? $this->requirements : [];

        return [
            'uuid' => $this->uuid,
            'quote_number' => $this->quote_number,
            'status' => $this->status,
            'status_label' => QuoteStatus::label((string) $this->status),
            'priority' => $this->priority,
            'customer_name' => trim($this->first_name.' '.$this->last_name),
            'customer_email' => $this->email,
            'customer_phone' => $this->phone,
            'organization' => $this->organization,
            'organization_ref' => $this->whenLoaded('organization', function (): ?array {
                $organization = $this->getRelation('organization');

                return $organization ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ] : null;
            }),
            'tenant' => $this->whenLoaded('tenant', fn (): ?array => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ] : null),
            'industry' => $this->industry,
            'product_name' => data_get($requirements, 'product_name')
                ?? ($this->relationLoaded('quotable') ? $this->quotable?->name : null),
            'sku' => data_get($requirements, 'sku'),
            'part_number' => data_get($requirements, 'part_number'),
            'quantity' => data_get($requirements, 'quantity'),
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'latest_response' => $this->whenLoaded(
                'latestResponse',
                fn (): ?QuoteResponseResource => $this->latestResponse
                    ? new QuoteResponseResource($this->latestResponse)
                    : null,
            ),
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at?->toISOString(),
            'first_response_at' => $this->first_response_at?->toISOString(),
            'last_response_at' => $this->last_response_at?->toISOString(),
            'response_due_at' => $this->response_due_at?->toISOString(),
        ];
    }

    private function isOverdue(): bool
    {
        return $this->response_due_at !== null
            && $this->response_due_at->isPast()
            && in_array($this->status, QuoteStatus::OPEN, true);
    }
}
