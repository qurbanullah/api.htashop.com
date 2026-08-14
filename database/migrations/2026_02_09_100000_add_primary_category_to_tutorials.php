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
        Schema::table('tutorials', function (Blueprint $table) {
            $table->foreignId('primary_category_id')->nullable()
                ->after('created_by')
                ->constrained('categories')
                ->onDelete('set null');

            $table->index('primary_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutorials', function (Blueprint $table) {
            $table->dropForeign(['primary_category_id']);
            $table->dropColumn('primary_category_id');
        });
    }
};
