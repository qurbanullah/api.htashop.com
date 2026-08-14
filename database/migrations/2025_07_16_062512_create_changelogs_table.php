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
        Schema::create('changelogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('version_number')->nullable();
            $table->timestamp('release_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sorting')->default(0);

            // Polymorphic relationship fields
            $table->morphs('changelogable');

            $table->timestamps();

            // Indexes for better performance
            $table->index('is_active');
            $table->index('release_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('changelogs');
    }
};
