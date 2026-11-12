<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse contact/address details moved into the polymorphic `addresses` row,
 * but older records kept a stale copy in the flat `warehouses` columns. Every
 * save now writes both, so this one-off backfill makes the flat columns truthful
 * for rows written before that change.
 *
 * Only non-empty address values are copied, so warehouses that never had an
 * address row keep whatever their columns already hold.
 */
return new class extends Migration
{
    /** Columns that exist on both `warehouses` and `addresses`. */
    private const MIRRORED = [
        'contact_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
    ];

    public function up(): void
    {
        DB::table('warehouses')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($warehouses): void {
                foreach ($warehouses as $warehouse) {
                    $address = DB::table('addresses')
                        ->where('addressable_type', 'App\\Models\\Warehouse')
                        ->where('addressable_id', $warehouse->id)
                        ->where('type', 'warehouse')
                        ->first();

                    if ($address === null) {
                        continue;
                    }

                    $updates = [];

                    foreach (self::MIRRORED as $column) {
                        $value = $address->{$column} ?? null;

                        if (is_string($value) && trim($value) !== '') {
                            $updates[$column] = $value;
                        }
                    }

                    if ($updates !== []) {
                        DB::table('warehouses')->where('id', $warehouse->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill only; the previous (stale) values are not recoverable.
    }
};
