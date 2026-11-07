<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: the carrier booking (TTN) and its printable label.
 *
 * `carrier_booking_id` is the courier's own identifier, kept so a booking can
 * be cancelled or reconciled without guessing from the tracking number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('carrier_booking_id', 100)->nullable()->after('tracking_url');
            $table->string('label_url', 1000)->nullable()->after('carrier_booking_id');
            $table->timestamp('booked_at')->nullable()->after('label_url');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['carrier_booking_id', 'label_url', 'booked_at']);
        });
    }
};
