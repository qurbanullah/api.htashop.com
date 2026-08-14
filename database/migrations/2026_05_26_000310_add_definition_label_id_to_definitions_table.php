<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historical filename retained so deployed environments keep a stable migration history.
        // The actual responsibility is to remove the legacy definition_label schema during the Label pivot.
        if (Schema::hasTable('definitions') && Schema::hasColumn('definitions', 'definition_label_id')) {
            Schema::table('definitions', function (Blueprint $table) {
                $table->dropIndex('definitions_tenant_label_index');
                $table->dropConstrainedForeignId('definition_label_id');
            });
        }

        Schema::dropIfExists('definition_labels');
    }

    public function down(): void
    {
    }
};
