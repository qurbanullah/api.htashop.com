<?php

namespace App\Actions\Quotes;

use App\Models\Industry;
use App\Models\Product;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateQuoteRequestAction
{
    /**
     * Create a new quote request.
     *
     * @throws Exception
     */
    public function execute(array $data): QuoteRequest
    {
        try {
            DB::beginTransaction();

            [$industryId, $industryLabel] = $this->resolveIndustry($data);
            $product = $this->resolveProduct($data);

            $quoteRequest = QuoteRequest::create([
                'tenant_id' => $product?->tenant_id,
                'organization_id' => $product?->organization_id,
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
                'industry' => $industryLabel,
                'industry_id' => $industryId,
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
                'response_due_at' => now()->addWeekday(),
            ]);

            QuoteActivity::create([
                'quote_request_id' => $quoteRequest->id,
                'type' => QuoteActivity::CREATED,
                'meta' => ['source' => data_get($data, 'source_page')],
            ]);

            DB::commit();

            return $quoteRequest;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create quote request: '.$e->getMessage());
        }
    }

    /**
     * Attribute the quote to the merchant that sells the quoted product.
     */
    private function resolveProduct(array $data): ?Product
    {
        if (data_get($data, 'quotable_type') !== Product::class || ! data_get($data, 'quotable_id')) {
            return null;
        }

        return Product::query()->find((int) data_get($data, 'quotable_id'));
    }

    /**
     * Keep the structured reference and the legacy label in sync.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveIndustry(array $data): array
    {
        $industryId = isset($data['industry_id']) ? (int) $data['industry_id'] : null;
        $industryLabel = isset($data['industry']) ? trim((string) $data['industry']) : null;

        if ($industryId !== null) {
            $industryLabel ??= Industry::query()->whereKey($industryId)->value('name');

            return [$industryId, $industryLabel];
        }

        if ($industryLabel === null || $industryLabel === '') {
            return [null, null];
        }

        $industryId = Industry::query()
            ->where('name', $industryLabel)
            ->orWhere('code', Str::slug($industryLabel))
            ->value('id');

        return [$industryId !== null ? (int) $industryId : null, $industryLabel];
    }
}
