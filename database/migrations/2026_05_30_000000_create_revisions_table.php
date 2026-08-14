<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('revisable_type');
            $table->unsignedBigInteger('revisable_id');
            $table->integer('revision_number')->default(1);
            $table->string('revision_type')->nullable();
            $table->string('reason')->nullable();
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_type')->nullable();
            $table->timestamps();

            $table->index(['revisable_type', 'revisable_id']);
            $table->index('revision_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
