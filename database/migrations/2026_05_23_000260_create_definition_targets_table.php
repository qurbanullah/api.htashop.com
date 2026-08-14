<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('definition_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('definitions')->cascadeOnDelete();
            $table->string('target_type');
            $table->timestamps();

            $table->unique(['definition_id', 'target_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('definition_targets');
    }
};
