<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table): void {
            // Set when the "your offer is about to expire" email is sent, so the
            // hourly job never reminds the same buyer twice.
            $table->timestamp('expiry_notified_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table): void {
            $table->dropColumn('expiry_notified_at');
        });
    }
};
