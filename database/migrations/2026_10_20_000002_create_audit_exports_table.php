<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_exports', function (Blueprint $table): void {
            $table->id();
            $table->date('export_date')->unique();
            $table->string('disk', 50)->default('observability');
            $table->string('path');
            $table->string('manifest_path');
            $table->unsignedBigInteger('rows')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('manifest_hash', 64)->nullable();
            $table->string('prev_manifest_hash', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'export_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_exports');
    }
};
