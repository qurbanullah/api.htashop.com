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
        Schema::create('dams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();

            // Polymorphic relation
            $table->string('damable_type')->nullable()->index();
            $table->unsignedBigInteger('damable_id')->nullable()->index();
            $table->string('collection_name')->nullable()->index();

            // Storage / object info
            $table->string('file_name');
            $table->string('disk')->nullable()->index();
            $table->string('bucket')->nullable()->index();
            $table->string('object_key')->nullable()->index();

            // File metadata
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('storage_class')->nullable();
            $table->string('checksum_sha256')->nullable()->index();
            $table->string('etag')->nullable();

            // Additional metadata / custom properties
            $table->json('custom_properties')->nullable();
            $table->json('metadata')->nullable();

            // provenance
            $table->string('origin_url')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();

            // versioning / derivation
            $table->unsignedBigInteger('derived_from_id')->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dams');
    }
};
