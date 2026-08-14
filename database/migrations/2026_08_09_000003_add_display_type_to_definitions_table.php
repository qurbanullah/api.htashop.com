<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('definitions', function (Blueprint $table) {
            $table->string('display_type')->nullable()->after('is_multi')->comment('swatch, button, dropdown, input, toggle');
            $table->string('swatch_type')->nullable()->after('display_type')->comment('color, image, text — for display_type=swatch');
        });
    }

    public function down(): void
    {
        Schema::table('definitions', function (Blueprint $table) {
            $table->dropColumn(['display_type', 'swatch_type']);
        });
    }
};
