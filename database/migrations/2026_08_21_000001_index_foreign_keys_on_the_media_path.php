<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three foreign keys that are queried on every media request and had no index
 * that could serve them.
 *
 * PostgreSQL indexes a primary key and a unique constraint, never a foreign
 * key column on its own. Two of these are *inside* a composite unique index
 * but as the trailing column, which cannot be used to look them up:
 *
 *   page_downloads             unique(page_id, media_file_id)
 *   education_level_page_download  unique(page_download_id, education_level_id)
 *
 * and pages.hero_image_id had nothing at all — `explain` confirmed a
 * sequential scan.
 *
 * Why it matters more than the table sizes suggest: App\Support\MediaAccess
 * asks all three before releasing a single byte of any image, document or
 * video, because deciding whether a file is public means finding every page
 * that shows it. That is the busiest route on the site — a class of thirty
 * opening the same page hits it simultaneously — and it was three sequential
 * scans deep before nginx was even handed the file.
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
