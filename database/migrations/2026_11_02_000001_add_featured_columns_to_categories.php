<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->unsignedSmallInteger('featured_rank')->default(0)->after('is_featured');

            // The storefront top nav filters on both columns.
            $table->index(['is_featured', 'featured_rank'], 'categories_featured_index');
        });

        // Move the old metadata flag onto the new column. Curated merchandising
        // data belongs in first-class columns: indexable, validatable, and
        // editable without rewriting JSON.
        DB::statement(
            'UPDATE categories SET is_featured = 1 WHERE JSON_EXTRACT(metadata, "$.show_top_category_nav") = true'
        );
        DB::statement(
            'UPDATE categories SET metadata = JSON_REMOVE(metadata, "$.show_top_category_nav") WHERE JSON_EXTRACT(metadata, "$.show_top_category_nav") IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'UPDATE categories SET metadata = JSON_SET(COALESCE(metadata, "{}"), "$.show_top_category_nav", true) WHERE is_featured = 1'
        );

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_featured_index');
            $table->dropColumn(['is_featured', 'featured_rank']);
        });
    }
};
