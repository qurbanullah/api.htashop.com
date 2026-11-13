<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Specifications carried a suggested unit as a free-text code ("mm", "kg").
 * That code is display-only and can drift from the UoM catalogue, so the real
 * reference is now a foreign key.
 *
 * The string column is kept: CSV exports/templates still read it, and it acts as
 * the human-readable label when no matching unit exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specifications', function (Blueprint $table) {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('unit')
                ->constrained('units')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Units are tenant-scoped, so the same code repeats. We map each code to the
     * lowest unit id, which keeps the mapping deterministic without assuming a
     * single tenant.
     */
    private function backfill(): void
    {
        $unitIdsByCode = [];

        DB::table('units')
            ->select('id', 'code')
            ->whereNotNull('code')
            ->orderBy('id')
            ->chunkById(500, function ($units) use (&$unitIdsByCode): void {
                foreach ($units as $unit) {
                    $unitIdsByCode[$unit->code] ??= (int) $unit->id;
                }
            });

        if ($unitIdsByCode === []) {
            return;
        }

        DB::table('specifications')
            ->select('id', 'unit')
            ->whereNotNull('unit')
            ->orderBy('id')
            ->chunkById(500, function ($specifications) use ($unitIdsByCode): void {
                foreach ($specifications as $specification) {
                    $unitId = $unitIdsByCode[$specification->unit] ?? null;

                    if ($unitId === null) {
                        continue;
                    }

                    DB::table('specifications')
                        ->where('id', $specification->id)
                        ->update(['unit_id' => $unitId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('specifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
