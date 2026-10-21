<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily', function (Blueprint $table): void {
            $table->id();
            // 0 = platform/global. A nullable tenant would defeat the unique
            // index on MariaDB (NULLs are distinct), so 0 is the sentinel.
            $table->unsignedBigInteger('tenant_id')->default(0);
            $table->date('metric_date');
            $table->string('entity_type', 50);
            $table->string('entity_id', 64);
            $table->string('metric_name', 50);
            $table->string('source', 30)->default('web');
            $table->unsignedBigInteger('count')->default(0);
            $table->decimal('value_sum', 18, 4)->default(0);
            $table->string('label', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'metric_date', 'entity_type', 'entity_id', 'metric_name', 'source'],
                'analytics_daily_unique',
            );
            $table->index(['metric_date', 'metric_name'], 'analytics_daily_date_metric_index');
            $table->index(['entity_type', 'entity_id', 'metric_date'], 'analytics_daily_entity_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
    }
};
