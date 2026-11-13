<?php

namespace App\Http\Resources\V1\Quote;

use App\Enums\Incoterm;
use App\Enums\PaymentTerms;
use App\Enums\QuoteResponseStatus;
use App\Enums\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'revision' => (int) $this->revision,
            'status' => $this->status,
            'status_label' => QuoteResponseStatus::label((string) $this->status),
            'subject' => $this->subject,
            'message' => $this->message,
            'terms' => $this->terms,
            'incoterm' => $this->incoterm,
            'incoterm_label' => Incoterm::labelFor($this->incoterm),
            'incoterm_location' => $this->incoterm_location,
            'payment_terms' => $this->payment_terms,
            'payment_terms_label' => PaymentTerms::labelFor($this->payment_terms),
            'warranty_months' => $this->warranty_months,
            'shipping_method' => $this->shipping_method,
            'shipping_method_label' => ShippingMethod::labelFor($this->shipping_method),
            'country_of_origin' => $this->country_of_origin,
            'hs_code' => $this->hs_code,
            'lead_time_days' => $this->lead_time_days,
            'estimated_ship_date' => $this->estimated_ship_date?->toDateString(),
            'port_of_loading' => $this->port_of_loading,
            'port_of_discharge' => $this->port_of_discharge,
            'partial_shipment_allowed' => (bool) $this->partial_shipment_allowed,
            'shipping_notes' => $this->shipping_notes,
            'package_count' => $this->package_count,
            'gross_weight_kg' => $this->gross_weight_kg !== null ? (float) $this->gross_weight_kg : null,
            'net_weight_kg' => $this->net_weight_kg !== null ? (float) $this->net_weight_kg : null,
            'package_dimensions' => $this->package_dimensions,
            'packaging_notes' => $this->packaging_notes,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal !== null ? (float) $this->subtotal : null,
            'discount_amount' => $this->discount_amount !== null ? (float) $this->discount_amount : null,
            'tax_amount' => $this->tax_amount !== null ? (float) $this->tax_amount : null,
            'shipping_amount' => $this->shipping_amount !== null ? (float) $this->shipping_amount : null,
            'total_amount' => $this->total_amount !== null ? (float) $this->total_amount : null,
            'validity_days' => (int) $this->validity_days,
            'expires_at' => $this->expires_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'declined_at' => $this->declined_at?->toISOString(),
            'superseded_at' => $this->superseded_at?->toISOString(),
            'internal_note' => (bool) $this->internal_note,
            'attachments' => $this->attachments ?? [],
            'is_expired' => $this->isExpired(),
            'sender' => $this->whenLoaded('sender', fn (): ?array => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
            ] : null),
            'items' => QuoteResponseItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
