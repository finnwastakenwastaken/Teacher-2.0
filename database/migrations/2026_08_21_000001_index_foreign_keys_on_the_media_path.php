<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three foreign keys queried on every media request, previously unindexed.
 * PostgreSQL doesn't index a foreign key column on its own, and two of these
 * were only the trailing column of a composite unique index (unusable for
 * lookup); pages.hero_image_id had nothing (confirmed via `explain`, a
 * sequential scan). App\Support\MediaAccess queries all three before
 * releasing any file byte — the busiest path on the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->index('hero_image_id');
        });

        Schema::table('page_downloads', function (Blueprint $table) {
            $table->index('media_file_id');
        });

        Schema::table('education_level_page_download', function (Blueprint $table) {
            $table->index('education_level_id');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['hero_image_id']);
        });

        Schema::table('page_downloads', function (Blueprint $table) {
            $table->dropIndex(['media_file_id']);
        });

        Schema::table('education_level_page_download', function (Blueprint $table) {
            $table->dropIndex(['education_level_id']);
        });
    }
};
