<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'category' => ['required', 'string', Rule::in(['technical', 'account', 'general', 'feature', 'bug'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
            'source_page' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function getTicketData(): array
    {
        $validated = $this->validated();

        $stypeMap = [
            'technical' => 'question',
            'account' => 'license',
            'general' => 'question',
            'feature' => 'question',
            'bug' => 'bug',
        ];

        $priorityMap = [
            'low' => 'low',
            'medium' => 'normal',
            'high' => 'high',
            'critical' => 'urgent',
        ];

        $categoryLabels = [
            'technical' => 'Technical Support',
            'account' => 'Account & Billing',
            'general' => 'General Inquiry',
            'feature' => 'Feature Request',
            'bug' => 'Bug Report',
        ];

        $additionalInformation = array_filter([
            'Submitted from public website support form.',
            'Portal access: unavailable for guest submissions.',
            'Selected category: ' . ($categoryLabels[$validated['category']] ?? $validated['category']),
            'Selected priority: ' . ucfirst($validated['priority']),
            !empty($validated['source_page']) ? 'Source page: ' . $validated['source_page'] : null,
        ]);

        return [
            'guest_name' => trim($validated['name']),
            'guest_email' => strtolower(trim($validated['email'])),
            'title' => trim($validated['subject']),
            'stype' => $stypeMap[$validated['category']] ?? 'question',
            'severity' => in_array($validated['category'], ['technical', 'bug'], true) ? 'major' : 'minor',
            'reproducibility' => in_array($validated['category'], ['technical', 'bug'], true) ? 'sometimes' : 'not-applicable',
            'priority' => $priorityMap[$validated['priority']] ?? 'normal',
            'status' => 'open',
            'is_visible' => false,
            'is_resolved' => false,
            'is_archived' => false,
            'is_locked' => false,
            'description' => trim($validated['message']),
            'additional_information' => implode("\n", $additionalInformation),
        ];
    }
}
