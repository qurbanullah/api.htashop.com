<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every measurement gets an explicit base unit.
 *
 * Until now the base was implicit: conversion factors are relative to a unit
 * with `factor = 1`, and `normalized_number` was assumed to be expressed in it.
 * That breaks the moment a catalogue has no factor-1 unit, several of them, or
 * the assumed base is removed. The base is now a first-class row reference.
 */
return new class extends Migration
{
    /** Canonical codes used to break ties between several factor-1 units. */
    private const PREFERRED = ['pc', 'm', 'kg', 's', 'K', 'A', 'V', 'W', 'Hz', 'l', 'B', 'pct'];

    public function up(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->foreignId('base_unit_id')
                ->nullable()
                ->after('code')
                ->constrained('units')
                ->nullOnDelete();
        });

        DB::table('measurements')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($measurements): void {
                foreach ($measurements as $measurement) {
                    $baseUnitId = $this->resolveBaseUnitId((int) $measurement->id);

                    if ($baseUnitId !== null) {
                        DB::table('measurements')
                            ->where('id', $measurement->id)
                            ->update(['base_unit_id' => $baseUnitId]);
                    }
                }
            });
    }

    /**
     * Base = a linear unit (factor 1, no offset), preferring the canonical code.
     */
    private function resolveBaseUnitId(int $measurementId): ?int
    {
        $units = DB::table('units')
            ->where('measurement_id', $measurementId)
            ->orderBy('id')
            ->get(['id', 'code', 'factor', 'offset']);

        if ($units->isEmpty()) {
            return null;
        }

        $linear = $units
            ->filter(fn ($unit): bool => (float) $unit->factor === 1.0 && (float) $unit->offset === 0.0)
            ->values();

        foreach (self::PREFERRED as $code) {
            $match = $linear->firstWhere('code', $code);

            if ($match !== null) {
                return (int) $match->id;
            }
        }

        return (int) ($linear->first() ?? $units->first())->id;
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('base_unit_id');
        });
    }
};
