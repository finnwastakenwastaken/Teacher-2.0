<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            // Introduction shown above the grid of children — TipTap JSON,
            // written only through Topic::writeContent(). No `content_text`
            // twin: that column only feeds pages.search_vector, and topics
            // don't enter search.
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
