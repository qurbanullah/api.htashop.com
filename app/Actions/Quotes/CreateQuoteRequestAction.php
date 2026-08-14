<?php

namespace App\Actions\Quotes;

use App\Models\QuoteRequest;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateQuoteRequestAction
{
    /**
     * Create a new quote request.
     *
     * @param array $data
     * @return QuoteRequest
     * @throws Exception
     */
    public function execute(array $data): QuoteRequest
    {
        try {
            DB::beginTransaction();

            $quoteRequest = QuoteRequest::create([
                // Personal Information
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'business_email' => $data['business_email'] ?? null,
                'phone' => $data['phone'] ?? null,
                // Business Information
                'organization' => $data['organization'],
                'department' => $data['department'] ?? null,
                'job_title' => $data['job_title'],
                'company_size' => $data['company_size'] ?? null,
                'industry' => $data['industry'] ?? null,
                // Location Information
                'country' => $data['country'],
                'state' => $data['state'] ?? null,
                'city' => $data['city'],
                'postal_code' => $data['postal_code'],
                // Project Details
                'application' => $data['application'] ?? null,
                'message' => $data['message'],
                'requirements' => $data['requirements'] ?? null,
                // Polymorphic
                'quotable_type' => $data['quotable_type'] ?? null,
                'quotable_id' => $data['quotable_id'] ?? null,
                // Metadata
                'ip_address' => $data['ip_address'] ?? request()->ip(),
                'user_agent' => $data['user_agent'] ?? request()->userAgent(),
                'source_page' => $data['source_page'] ?? request()->header('referer'),
                'status' => 'pending',
            ]);

            DB::commit();

            return $quoteRequest;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Failed to create quote request: " . $e->getMessage());
        }
    }
}
