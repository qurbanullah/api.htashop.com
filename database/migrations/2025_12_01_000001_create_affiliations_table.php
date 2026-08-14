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
        if (!Schema::hasTable('affiliations')) {
            Schema::create('affiliations', function (Blueprint $table) {
                $table->id();
                $table->morphs('affiliatable'); // affiliatable_type, affiliatable_id
                $table->string('institution')->nullable();
                $table->string('department')->nullable();
                $table->string('position')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_current')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                // Indexes
                $table->index('is_current');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliations');
    }
};
