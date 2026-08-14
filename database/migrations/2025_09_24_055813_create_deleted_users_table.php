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
        Schema::create('deleted_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id')->unique(); // Original user ID
            $table->string('name')->nullable(); // User's name at deletion time
            $table->string('email')->nullable(); // User's email at deletion time (can be anonymized)
            $table->timestamp('soft_deleted_at')->nullable(); // When user was soft deleted
            $table->timestamp('permanently_deleted_at'); // When user was permanently deleted
            $table->json('deletion_reason')->nullable(); // GDPR compliance reason
            $table->string('anonymized_identifier')->nullable(); // Anonymous identifier like "User #12345"
            $table->timestamps();

            $table->index('original_user_id');
            $table->index(['permanently_deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_users');
    }
};
