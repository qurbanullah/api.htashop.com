<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('business_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('organization');
            $table->string('department')->nullable();
            $table->string('job_title');
            $table->string('company_size')->nullable();
            $table->string('industry')->nullable();
            $table->string('country');
            $table->string('state')->nullable();
            $table->string('city');
            $table->string('postal_code');
            $table->string('application')->nullable();
            $table->text('message');
            $table->json('requirements')->nullable();
            $table->nullableMorphs('quotable');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('source_page')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('quoted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
