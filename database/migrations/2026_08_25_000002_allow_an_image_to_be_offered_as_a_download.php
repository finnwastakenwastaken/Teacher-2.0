<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A poster, a scanned worksheet or a diagram can be handed out.
 *
 * MediaLibrary::ingest() picks the library from the sniffed MIME alone, so
 * anything raster becomes an `images` row — and `page_downloads.media_file_id`
 * pointed only at `media_files`. An image therefore could not be offered as a
 * download at any layer.
 *
 * The fix is one attachment able to name either library, not a second copy of
 * the picture in `media_files`. One image, two ways to reach it: embedded in a
 * lesson, offered as a printable handout, or both — which is a real case, and
 * an authored "this one is for downloading" flag would have forced the owner
 * to pick one and call the other a lie.
 *
 * A polymorphic pair was rejected: it gives up the foreign key, and
 * restrictOnDelete on that key is what currently stops a file vanishing out
 * from under a page. Two nullable keys keep both constraints and let the
 * database enforce the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_downloads', function (Blueprint $table) {
            // Blocks rather than cascades, exactly like media_file_id: an
            // image still offered on a page must not be deletable, and
            // Image::extraDependents() turns the refusal into a sentence
            // naming the pages.
            $table->foreignId('image_id')
                ->nullable()
                ->after('media_file_id')
                ->constrained('images')
                ->restrictOnDelete();

            // Its own index, for the same reason media_file_id has one: the
            // partial unique below leads with page_id, and every question
            // asked of this column — is this image still offered anywhere,
            // may it be deleted, may an anonymous visitor fetch it — filters
            // on image_id alone.
            $table->index('image_id');
        });

        DB::statement('alter table page_downloads alter column media_file_id drop not null');

        // Exactly one of the two, always. Neither set is an attachment that
        // offers nothing; both set is an attachment whose two halves can
        // disagree about what a student gets. `<>` on two booleans is xor,
        // and reads as "one of these is null and the other is not".
        DB::statement(<<<'SQL'
            alter table page_downloads
            add constraint page_downloads_exactly_one_source
            check ((media_file_id is null) <> (image_id is null))
        SQL);

        // The old index was unique on (page_id, media_file_id) and cannot
        // survive the column becoming nullable: PostgreSQL treats NULLs as
        // distinct in a unique index by default, so every image attachment
        // would carry a null media_file_id that is unique from every other
        // one — and the same image could be attached to the same page any
        // number of times, with the index reporting no conflict at all.
        //
        // Two partial indexes rather than one index with NULLS NOT DISTINCT
        // (available on this PostgreSQL, but a subtler thing to read): each
        // index says plainly which column it is about, and neither ever
        // contains a null.
        // A constraint, not a bare index — Laravel's `unique()` compiles to
        // `add constraint ... unique` on PostgreSQL — so it is dropped as one.
        DB::statement('alter table page_downloads drop constraint page_downloads_page_id_media_file_id_unique');

        DB::statement(<<<'SQL'
            create unique index page_downloads_page_file_unique
            on page_downloads (page_id, media_file_id)
            where media_file_id is not null
        SQL);

        DB::statement(<<<'SQL'
            create unique index page_downloads_page_image_unique
            on page_downloads (page_id, image_id)
            where image_id is not null
        SQL);
    }

    /**
     * Rolling back discards the attachments the old shape cannot represent.
     *
     * An image-backed download has no media_file_id, and the column is about
     * to be NOT NULL again — there is no value to put there and no second
     * copy of the picture in media_files to point at. So the rows go, and the
     * images themselves stay in the library untouched: a rollback un-offers
     * them, it does not delete anything the owner uploaded.
     */
    public function down(): void
    {
        DB::table('page_downloads')->whereNotNull('image_id')->delete();

        DB::statement('drop index page_downloads_page_image_unique');
        DB::statement('drop index page_downloads_page_file_unique');
        DB::statement('alter table page_downloads drop constraint page_downloads_exactly_one_source');

        DB::statement('alter table page_downloads alter column media_file_id set not null');

        DB::statement(<<<'SQL'
            alter table page_downloads
            add constraint page_downloads_page_id_media_file_id_unique
            unique (page_id, media_file_id)
        SQL);

        Schema::table('page_downloads', function (Blueprint $table) {
            $table->dropIndex(['image_id']);
            $table->dropConstrainedForeignId('image_id');
        });
    }
};
