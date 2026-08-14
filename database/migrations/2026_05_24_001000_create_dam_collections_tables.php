<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dam_collections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('kind')->default('label')->index();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('dam_collection_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dam_id')->index();
            $table->unsignedBigInteger('dam_collection_id')->index();
            $table->integer('sort_order')->nullable()->index();
            $table->timestamps();

            $table->unique(['dam_id', 'dam_collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dam_collection_items');
        Schema::dropIfExists('dam_collections');
    }
};
