<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last ten published bodies of a page, so a publish can be undone.
 *
 * A row is written by App\Models\Page::writeContent() and holds the body it
 * *replaced*, not the one it wrote — what the owner wants back is what was
 * there before, and what is there now is already on the page row. An autosave
 * writes nothing here: a concept is not an edit anybody chose to make, and the
 * debounce fires while they are still typing (see Page::writeDraft()).
 *
 * Two things are deliberately absent.
 *
 * There is no copy of `page_media_references`. Those rows are derived and are
 * rebuilt wholesale on every write, so a restore re-derives them by going back
 * through writeContent() — exactly as App\Actions\DuplicatePage does. Storing
 * them here would be a second, authored copy of derived data that could drift
 * from the document beside it, and restoring from that copy could publish a
 * file the restored body does not actually show.
 *
 * And the history is capped at ten per page, pruned inside the same
 * transaction as the write. That cap is the design rather than a setting: ten
 * jsonb bodies per page ride along in `database.sql` inside every backup
 * archive, and an uncapped table would make every archive grow with the number
 * of times somebody pressed save.
 *
 * cascadeOnDelete, which is not the rule elsewhere in this schema — a delete
 * that would orphan data blocks and reports what depends on it. A revision is
 * not a dependent: it is a previous state of the page itself, owned by it and
 * worthless without it, like `page_media_references` and unlike a download.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();

            // Nullable to match `pages.content`, which is a page nobody has
            // written yet — but nothing writes a null here, because a body
            // that was never written is not a version worth going back to.
            $table->jsonb('content')->nullable();

            // Kept beside the document rather than re-derived on restore. It
            // is what the search vector is built from, and deriving it twice
            // from the same document in two places is one more thing that can
            // drift; App\Support\PageContent::toPlainText() runs once, when
            // the body was published.
            $table->text('content_text')->nullable();

            // created_at is the label the owner reads: versions are otherwise
            // indistinguishable. updated_at comes with it and is never
            // written — a revision is a snapshot, so there is nothing to
            // update.
            $table->timestamps();

            // The list is always "this page's, newest first", and the prune
            // that keeps it at ten asks the same question.
            $table->index(['page_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
