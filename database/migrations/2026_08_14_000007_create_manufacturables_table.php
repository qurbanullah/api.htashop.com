<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->cascadeOnDelete();
            $table->morphs('manufacturable');
            $table->timestamps();

            $table->unique(['manufacturer_id', 'manufacturable_id', 'manufacturable_type'], 'manufacturable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturables');
    }
};
