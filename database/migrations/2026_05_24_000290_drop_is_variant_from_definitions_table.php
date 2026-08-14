<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('definitions') || !Schema::hasColumn('definitions', 'is_variant')) {
            return;
        }

        Schema::table('definitions', function (Blueprint $table) {
            $table->dropColumn('is_variant');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('definitions') || Schema::hasColumn('definitions', 'is_variant')) {
            return;
        }

        Schema::table('definitions', function (Blueprint $table) {
            $table->boolean('is_variant')->default(false)->after('is_searchable');
        });
    }
};
