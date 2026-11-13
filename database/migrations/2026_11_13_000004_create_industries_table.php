<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-industry vocabulary (LinkedIn's 147-industry taxonomy).
 *
 * Industries describe the sector a *company* operates in (buyer vertical) or a
 * manufacturer belongs to. They are deliberately separate from `categories`,
 * which describe what we sell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->foreignId('parent_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
