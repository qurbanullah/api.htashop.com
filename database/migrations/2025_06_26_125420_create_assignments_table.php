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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_by')->index()->constrained('users');
            $table->foreignId('assigned_to')->index()->constrained('users');
            $table->morphs('assignable'); // Creates assignable_type and assignable_id
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            // $table->index(['assignable_type', 'assignable_id']);
            // $table->index(['assigned_to', 'is_active']);
            // $table->index('assigned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
