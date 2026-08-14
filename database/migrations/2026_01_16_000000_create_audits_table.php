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
        Schema::create('audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Keep a single 'event' column (e.g., created/updated/role_attached)
            // Use 191 char limit to avoid index length issues on utf8mb4
            $table->string('event', 191);
            // Polymorphic relation to the audited model
            $table->morphs('auditable');
            $table->string('auditable_type_name', 191)->nullable();
            $table->json('meta')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // Index optimized for common filters: by model, event and actor
            $table->index(['event', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
