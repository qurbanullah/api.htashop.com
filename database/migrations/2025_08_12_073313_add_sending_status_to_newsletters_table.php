<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            // For SQLite compatibility, we need to handle indexes differently
            if (Schema::hasIndex('newsletters', 'newsletters_status_scheduled_at_index')) {
                $table->dropIndex('newsletters_status_scheduled_at_index');
            }
        });

        Schema::table('newsletters', function (Blueprint $table) {
            // Drop the existing enum column
            $table->dropColumn('status');
        });

        Schema::table('newsletters', function (Blueprint $table) {
            // Add the new enum column with 'sending' status
            $table->enum('status', ['draft', 'scheduled', 'published', 'sending', 'sent'])->default('draft')->after('featured_image');

            // Re-create the index
            $table->index('scheduled_at', 'newsletters_status_scheduled_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            // Drop the index first
            if (Schema::hasIndex('newsletters', 'newsletters_status_scheduled_at_index')) {
                $table->dropIndex('newsletters_status_scheduled_at_index');
            }
        });

        Schema::table('newsletters', function (Blueprint $table) {
            // Drop the new enum column
            $table->dropColumn('status');
        });

        Schema::table('newsletters', function (Blueprint $table) {
            // Restore the original enum column
            $table->enum('status', ['draft', 'scheduled', 'published', 'sent'])->default('draft')->after('featured_image');

            // Re-create the index
            $table->index('scheduled_at', 'newsletters_status_scheduled_at_index');
        });
    }
};
