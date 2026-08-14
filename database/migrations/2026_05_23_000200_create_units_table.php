<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_id')->constrained('measurements')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('symbol')->nullable();
            $table->decimal('factor', 20, 10)->default(1);
            $table->decimal('offset', 20, 10)->default(0);
            $table->unsignedTinyInteger('precision')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['measurement_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
