<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bom_request_id')->constrained('bom_requests')->cascadeOnDelete();
            $table->string('part_name');
            $table->string('part_number')->nullable();
            $table->text('specification')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->nullable();
            $table->decimal('target_unit_price', 15, 2)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('bom_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_lines');
    }
};
