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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // en, ur, de, etc.
            $table->string('name'); // English, Urdu, Deutsch
            $table->string('native_name'); // English, اردو, Deutsch
            $table->string('flag_emoji', 10)->nullable(); // 🇬🇧, 🇵🇰, 🇩🇪
            $table->boolean('is_rtl')->default(false); // RTL for Urdu, Arabic, etc.
            $table->boolean('is_active')->default(true); // Enable/disable languages
            $table->boolean('is_default')->default(false); // Default language (English)
            $table->integer('sort_order')->default(0); // Display order
            $table->timestamps();

            // Indexes
            $table->index(['code', 'is_active']);
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
