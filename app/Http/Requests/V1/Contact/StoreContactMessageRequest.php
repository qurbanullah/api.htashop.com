<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Contact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'order_uuid' => ['nullable', 'string', 'max:36'],
            'country' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', Rule::in(['general', 'sales', 'support', 'demo', 'partnership', 'order', 'shipping', 'returns', 'product', 'supplier', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['required', 'accepted'],
            'source_page' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function getContactData(): array
    {
        $validated = $this->validated();

        $subjectLabels = [
            'general' => 'General Inquiry',
            'sales' => 'Sales Question',
            'support' => 'Customer Support',
            'demo' => 'Request Demo',
            'partnership' => 'Partnership',
            'order' => 'Order Support',
            'shipping' => 'Shipping & Delivery',
            'returns' => 'Returns & Refunds',
            'product' => 'Product Question',
            'supplier' => 'B2B / Become a Supplier',
            'other' => 'Other',
        ];

        return [
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'order_uuid' => $validated['order_uuid'] ?? null,
            'subject' => $subjectLabels[$validated['subject']] ?? 'General Inquiry',
            'message' => trim($validated['message']),
            'status' => 'new',
            'metadata' => array_filter([
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'company' => $validated['company'] ?? null,
                'country' => $validated['country'] ?? null,
                'subject_key' => $validated['subject'],
                'consent' => true,
                'source_page' => $validated['source_page'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''),
        ];
    }
}
