<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            $table->string('role', 20); // system | user | assistant | tool
            $table->longText('content')->nullable();

            // Sources the answer was grounded in.
            $table->json('citations')->nullable();
            // Tool names/args/status invoked while producing the answer.
            $table->json('tool_calls')->nullable();

            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);

            $table->string('feedback', 20)->nullable();
            $table->string('feedback_comment', 1000)->nullable();
            $table->timestamp('feedback_at')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('feedback');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
