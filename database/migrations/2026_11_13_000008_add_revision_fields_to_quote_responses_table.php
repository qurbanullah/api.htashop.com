<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quote responses become versioned documents with own totals, validity and
 * buyer decision timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1)->after('status');
            $table->timestamp('expires_at')->nullable()->after('validity_days');
            $table->decimal('subtotal', 14, 2)->nullable()->after('total_amount');
            $table->decimal('tax_amount', 14, 2)->nullable()->after('subtotal');
            $table->decimal('shipping_amount', 14, 2)->nullable()->after('tax_amount');
            $table->decimal('discount_amount', 14, 2)->nullable()->after('shipping_amount');
            $table->text('terms')->nullable()->after('message');
            $table->timestamp('accepted_at')->nullable()->after('viewed_at');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->timestamp('superseded_at')->nullable()->after('declined_at');
            $table->boolean('internal_note')->default(false)->after('superseded_at');
        });

        DB::table('quote_responses')
            ->whereNotNull('sent_at')
            ->whereNull('expires_at')
            ->select('id', 'sent_at', 'validity_days')
            ->orderBy('id')
            ->chunk(200, function ($responses): void {
                foreach ($responses as $response) {
                    $sentAt = Carbon::parse($response->sent_at);
                    $days = max(1, (int) ($response->validity_days ?: 30));

                    DB::table('quote_responses')->where('id', $response->id)->update([
                        'expires_at' => $sentAt->copy()->addDays($days),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table): void {
            $table->dropColumn([
                'revision',
                'expires_at',
                'subtotal',
                'tax_amount',
                'shipping_amount',
                'discount_amount',
                'terms',
                'accepted_at',
                'declined_at',
                'superseded_at',
                'internal_note',
            ]);
        });
    }
};
