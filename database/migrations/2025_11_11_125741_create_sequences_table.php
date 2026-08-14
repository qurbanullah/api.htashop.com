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
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->morphs('sequenceable'); // Creates sequenceable_type and sequenceable_id
            $table->integer('year');
            $table->integer('last_number')->default(0);
            $table->string('prefix', 20)->nullable(); // Optional prefix for the sequence
            $table->timestamps();

            // Ensure unique combination of sequenceable and year
            $table->unique(['sequenceable_type', 'sequenceable_id', 'year'], 'sequences_unique');
            $table->index(['sequenceable_type', 'sequenceable_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
