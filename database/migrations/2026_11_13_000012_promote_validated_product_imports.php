<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before `validated` existed, a successful parse left the import in
 * `validating`. That value is treated as in-flight by the client, which hid the
 * commit action and polled forever. Every such row is post-parse, so promote
 * them and restore the validation progress counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_imports')
            ->where('status', 'validating')
            ->update([
                'status' => 'validated',
                'processed_rows' => DB::raw('total_rows'),
            ]);
    }

    public function down(): void
    {
        // One-way: reverting would put legacy imports back into the broken
        // in-flight state and hide their commit action again.
    }
};
