<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dams', function (Blueprint $table) {
            $table->integer('sort_order')->nullable()->after('collection_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('dams', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
