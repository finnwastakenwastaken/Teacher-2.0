<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The concept: a page body that has been written down but not published.
 *
 * A separate column rather than a flag on `content`, and that separation is
 * the whole point of the feature. Everything derived from a page body hangs
 * off `content`: `page_media_references` is what publishes an embedded file to
 * anonymous visitors (App\Support\MediaAccess::isPubliclyReachable), and
 * `content_text` feeds the `search_vector` trigger. An autosave that wrote
 * through Page::writeContent() would therefore publish every image in a
 * half-written body — including one the owner pasted and deleted a minute
 * later — and put the unfinished page in the public search box.
 *
 * So nothing reads this column but the editor. It is written by
 * Page::writeDraft(), which never touches the derived tables, and it becomes
 * real content only through Page::promoteDraft(), which goes through
 * writeContent() exactly once.
 *
 * `is_hidden` is deliberately not reused as the draft flag. A hidden page is a
 * finished page the owner has not linked yet — it renders at its direct URL on
 * purpose, and App\Actions\DuplicatePage starts a copy hidden. A concept is a
 * body that has never been shown to anyone. Two different states.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Non-null exactly when an unpublished concept exists. There is
            // one row, not a history: a revision log is a second table, a
            // retention policy and a screen nobody asked for.
            $table->jsonb('draft_content')->nullable()->after('content_text');

            // What the banner on the editor shows the owner. Without a time
            // "you have unsaved changes" is unanswerable — they cannot tell a
            // concept from this morning from one from last term.
            $table->timestamp('draft_saved_at')->nullable()->after('draft_content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['draft_content', 'draft_saved_at']);
        });
    }
};
