<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->json('pricing')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('validity_days')->default(30);
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('software_id')->nullable();
            $table->unsignedBigInteger('ltype_id')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            $table->index('quote_request_id');
            $table->index('sent_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_responses');
    }
};
