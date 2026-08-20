<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            // An introduction to the subject, shown above the grid of
            // children. TipTap JSON, like a page body, and written only
            // through Topic::writeContent().
            //
            // Deliberately without the `content_text` twin that pages carry.
            // That column exists to feed pages.search_vector, and topics do
            // not enter search results; a second derived column that nothing
            // reads is one more thing that can drift out of step with the
            // document it claims to describe. Adding topics to search means
            // a column, a trigger and a product decision, together.
            $table->jsonb('content')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
