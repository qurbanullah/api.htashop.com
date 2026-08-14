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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->foreignId('user_id')->index();
            $table->string('title')->index();
            $table->string('slug')->index();
            $table->string('stype')->nullable()->index();
            $table->string('severity')->nullable()->index();
            $table->string('reproducibility')->nullable()->index();
            $table->string('priority')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->boolean('is_visible')->nullable()->default(true);
            $table->boolean('is_resolved')->nullable()->default(false);
            $table->boolean('is_locked')->nullable()->default(false);
            $table->boolean('is_archived')->nullable()->default(false);
            $table->text('description')->nullable();
            $table->text('steps_to_reproduce')->nullable();
            $table->text('additional_information')->nullable();
            $table->timestamp('resolved_on')->nullable();
            $table->timestamp('archived_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
