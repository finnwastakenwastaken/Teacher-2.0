<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Page banner — a column, not a body node, since it's page
            // furniture that survives every body edit. restrictOnDelete like
            // every other media reference: blocks and reports usage rather
            // than leaving a hole.
            $table->foreignId('hero_image_id')
                ->nullable()
                ->after('is_hidden')
                ->constrained('images')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hero_image_id');
        });
    }
};
