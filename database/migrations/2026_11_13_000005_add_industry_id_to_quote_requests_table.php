<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quote requests asked for the buyer's industry as free text.
 *
 * The text column is kept (submissions still accept it and older rows may use
 * it), but the structured reference is `industry_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->foreignId('industry_id')
                ->nullable()
                ->after('industry')
                ->constrained('industries')
                ->nullOnDelete();
        });

        $this->backfillFromLegacyText();
    }

    /**
     * Map existing free-text values onto the catalogue when it is already
     * seeded (production upgrade order is not guaranteed).
     */
    private function backfillFromLegacyText(): void
    {
        if (! Schema::hasTable('industries') || DB::table('industries')->count() === 0) {
            return;
        }

        $idsByName = [];
        $idsByCode = [];

        foreach (DB::table('industries')->get(['id', 'code', 'name']) as $industry) {
            $idsByName[mb_strtolower($industry->name)] = (int) $industry->id;
            $idsByCode[$industry->code] = (int) $industry->id;
        }

        DB::table('quote_requests')
            ->select('id', 'industry')
            ->whereNull('industry_id')
            ->whereNotNull('industry')
            ->orderBy('id')
            ->chunkById(200, function ($requests) use ($idsByName, $idsByCode): void {
                foreach ($requests as $request) {
                    $value = mb_strtolower(trim((string) $request->industry));
                    $slug = str_replace([' ', '_'], '-', preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '');

                    $industryId = $idsByName[$value] ?? $idsByCode[trim($slug, '-')] ?? null;

                    if ($industryId === null) {
                        continue;
                    }

                    DB::table('quote_requests')
                        ->where('id', $request->id)
                        ->update(['industry_id' => $industryId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('industry_id');
        });
    }
};
