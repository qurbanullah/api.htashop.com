<?php

namespace App\Http\Requests\V1\Quote;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            // Personal Information
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'business_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            // Business Information
            'organization' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'job_title' => 'required|string|max:255',
            'company_size' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            // Location Information
            'country' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            // Project Details
            'application' => 'nullable|string|max:255',
            'message' => 'required|string|min:10',
            'requirements' => 'nullable|array',
            // Polymorphic
            'quotable_type' => 'nullable|string',
            'quotable_id' => 'nullable|integer',
            // Metadata
            'source_page' => 'nullable|string|max:500',
        
        ];
    }
}
