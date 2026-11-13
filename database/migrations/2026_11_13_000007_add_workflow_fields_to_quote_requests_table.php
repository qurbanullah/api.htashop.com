<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quote requests become a first-class sales object: scoped to a merchant,
 * numbered, assignable, and with an explicit workflow status.
 *
 * The legacy `quoted` / `converted` statuses are mapped onto the new
 * `replied` / `accepted` vocabulary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->string('quote_number', 32)->nullable()->after('uuid');
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->after('assigned_to');
            $table->timestamp('first_response_at')->nullable()->after('quoted_at');
            $table->timestamp('last_response_at')->nullable()->after('first_response_at');
            $table->timestamp('response_due_at')->nullable()->after('last_response_at');
            $table->timestamp('accepted_at')->nullable()->after('response_due_at');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->timestamp('cancelled_at')->nullable()->after('declined_at');
            $table->string('cancelled_reason', 255)->nullable()->after('cancelled_at');

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['organization_id', 'status']);
            $table->index('assigned_to');
        });

        $this->backfillStatuses();
        $this->backfillOwnership();
        $this->backfillNumbers();
        $this->backfillResponseDates();

        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->unique('quote_number');
        });
    }

    private function backfillStatuses(): void
    {
        DB::table('quote_requests')->where('status', 'quoted')->update(['status' => 'replied']);
        DB::table('quote_requests')->where('status', 'converted')->update(['status' => 'accepted']);
    }

    /**
     * Attribute each quote to the merchant that sells the quoted product.
     */
    private function backfillOwnership(): void
    {
        DB::table('quote_requests')
            ->whereIn('quotable_type', ['App\\Models\\Product'])
            ->whereNotNull('quotable_id')
            ->select('id', 'quotable_id')
            ->orderBy('id')
            ->chunk(200, function ($quotes): void {
                $products = DB::table('products')
                    ->whereIn('id', $quotes->pluck('quotable_id')->unique()->all())
                    ->get(['id', 'tenant_id', 'organization_id'])
                    ->keyBy('id');

                foreach ($quotes as $quote) {
                    $product = $products->get($quote->quotable_id);

                    if (! $product) {
                        continue;
                    }

                    DB::table('quote_requests')->where('id', $quote->id)->update([
                        'tenant_id' => $product->tenant_id,
                        'organization_id' => $product->organization_id,
                    ]);
                }
            });
    }

    private function backfillNumbers(): void
    {
        DB::table('quote_requests')
            ->select('id', 'created_at')
            ->orderBy('id')
            ->chunk(200, function ($quotes): void {
                foreach ($quotes as $quote) {
                    $year = $quote->created_at
                        ? substr((string) $quote->created_at, 0, 4)
                        : now()->format('Y');

                    DB::table('quote_requests')->where('id', $quote->id)->update([
                        'quote_number' => sprintf('Q-%s-%06d', $year, $quote->id),
                    ]);
                }
            });
    }

    private function backfillResponseDates(): void
    {
        DB::table('quote_requests')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('response_due_at')
            ->select('id', 'created_at')
            ->orderBy('id')
            ->chunk(200, function ($quotes): void {
                foreach ($quotes as $quote) {
                    $due = Carbon::parse($quote->created_at ?? now())->addWeekday();

                    DB::table('quote_requests')->where('id', $quote->id)->update([
                        'response_due_at' => $due,
                    ]);
                }
            });

        DB::table('quote_responses')
            ->whereNotNull('sent_at')
            ->select('quote_request_id', DB::raw('MIN(sent_at) as first_sent'), DB::raw('MAX(sent_at) as last_sent'))
            ->groupBy('quote_request_id')
            ->orderBy('quote_request_id')
            ->chunk(200, function ($responses): void {
                foreach ($responses as $response) {
                    DB::table('quote_requests')->where('id', $response->quote_request_id)->update([
                        'first_response_at' => $response->first_sent,
                        'last_response_at' => $response->last_sent,
                        'response_due_at' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->dropUnique(['quote_number']);
            $table->dropIndex(['tenant_id', 'status', 'created_at']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['assigned_to']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn([
                'quote_number',
                'priority',
                'first_response_at',
                'last_response_at',
                'response_due_at',
                'accepted_at',
                'declined_at',
                'cancelled_at',
                'cancelled_reason',
            ]);
        });
    }
};
