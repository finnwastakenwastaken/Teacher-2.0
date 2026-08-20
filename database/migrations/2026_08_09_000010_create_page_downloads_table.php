<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The downloads section at the bottom of a page.
 *
 * Relational rather than part of the TipTap body, because this is the one
 * feature that justifies the whole project: the same worksheet in several
 * track-specific variants, grouped by level. That needs to be queryable and
 * countable, which a nested JSON document is not.
 *
 * Level tags hang off the *attachment*, not the file: the same PDF can be
 * offered as "HAVO + VWO" on one page and "VWO" on another, and neither page
 * should be able to change what the other says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_downloads', function (Blueprint $table) {
            $table->id();
            // Addressed publicly by ULID like the media libraries, so the
            // download route cannot be walked by counting upwards.
            $table->ulid('ulid')->unique();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            // Blocks rather than cascades: deleting a file still offered as a
            // download must fail and say which pages use it, not silently
            // empty their downloads section.
            $table->foreignId('media_file_id')->constrained()->restrictOnDelete();
            // Optional display name. Falls back to the file's own filename.
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // An aggregate tally and the only counter in the system. No
            // visitor data is attached to it — see the technical reference on privacy.
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->timestamps();

            // One page offers a given file once. Two identical cards in the
            // same list is always a mistake, and the fix is to edit the
            // existing attachment rather than add a second.
            $table->unique(['page_id', 'media_file_id']);
            $table->index(['page_id', 'sort_order']);
        });

        Schema::create('education_level_page_download', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_download_id')->constrained()->cascadeOnDelete();
            // Restricts: a level still in use cannot be deleted outright, it
            // has to be merged into another one first.
            $table->foreignId('education_level_id')->constrained()->restrictOnDelete();

            $table->unique(['page_download_id', 'education_level_id'], 'page_download_level_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_level_page_download');
        Schema::dropIfExists('page_downloads');
    }
};
