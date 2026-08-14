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
        // Simplified avatars table - no separate variants table needed
        Schema::create('avatars', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation - allows avatars for User, Team, Organization, etc.
            $table->morphs('avatareable'); // Creates avatareable_id and avatareable_type with composite index

            // Avatar type/size: 'original', 'thumb', 'small', 'medium', 'large'
            $table->string('type')->default('medium')->index();

            // S3 file path
            $table->string('path')->comment('S3 path to avatar file');

            // Metadata
            $table->string('original_filename')->comment('Original uploaded filename');
            $table->string('mime_type')->default('image/jpeg');

            // File metadata
            $table->integer('file_size')->nullable()->comment('File size in bytes');
            $table->integer('width')->nullable()->comment('Image width in pixels');
            $table->integer('height')->nullable()->comment('Image height in pixels');

            // Expandable metadata for future use
            $table->json('metadata')->nullable()->comment('Additional metadata for future use');

            // Tracking
            $table->timestamps();
            $table->softDeletes();

            // Indexes - one avatar per type per avatareable
            $table->unique(['avatareable_id', 'avatareable_type', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avatars');
    }
};
