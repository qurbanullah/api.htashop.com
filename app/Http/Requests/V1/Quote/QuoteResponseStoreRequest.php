<?php

namespace App\Http\Requests\V1\Quote;

use App\Enums\Incoterm;
use App\Enums\PaymentTerms;
use App\Enums\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteResponseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'terms' => ['nullable', 'string'],
            'incoterm' => ['nullable', 'string', Rule::in(Incoterm::values())],
            'incoterm_location' => ['nullable', 'string', 'max:120'],
            'payment_terms' => ['nullable', 'string', Rule::in(PaymentTerms::values())],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'shipping_method' => ['nullable', 'string', Rule::in(ShippingMethod::values())],
            'country_of_origin' => ['nullable', 'string', 'max:64'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'estimated_ship_date' => ['nullable', 'date'],
            'port_of_loading' => ['nullable', 'string', 'max:120'],
            'port_of_discharge' => ['nullable', 'string', 'max:120'],
            'partial_shipment_allowed' => ['nullable', 'boolean'],
            'shipping_notes' => ['nullable', 'string', 'max:2000'],
            'package_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'gross_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'net_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'package_dimensions' => ['nullable', 'string', 'max:120'],
            'packaging_notes' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'send' => ['nullable', 'boolean'],
            'internal_note' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:variants,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.part_number' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }
}
