<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('definitions') || !Schema::hasTable('definition_targets') || !Schema::hasColumn('definitions', 'is_variant')) {
            return;
        }

        DB::table('definitions')
            ->leftJoin('definition_targets', 'definitions.id', '=', 'definition_targets.definition_id')
            ->whereNull('definition_targets.id')
            ->select('definitions.id', 'definitions.is_variant')
            ->orderBy('definitions.id')
            ->get()
            ->each(function ($definition): void {
                DB::table('definition_targets')->insert([
                    'definition_id' => $definition->id,
                    'target_type' => $definition->is_variant ? 'variant' : 'product',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
    }
};
