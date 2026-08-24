<?php

namespace App\Console\Commands;

use App\Support\ContentLanguage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-derive every page's search vector.
 *
 * The trigger only fires when a page is written, so changing the content
 * language leaves every existing row stemmed by the old rules. Nothing looks
 * broken — search simply keeps missing words it should find, which is the
 * worst shape a bug can take.
 *
 * SiteSettingsController runs this automatically when the setting changes.
 * It exists as a command as well because that is the only way to fix an
 * index after restoring a backup taken under a different setting, or after
 * editing the row by hand.
 *
 * The idiom is the migration's own: `set title = title` touches a column the
 * trigger watches, so the trigger recomputes the vector without any of the
 * SQL being repeated here.
 */
class ReindexSearchCommand extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Rebuild the full-text search index for every page';

    public function handle(): int
    {
        $configuration = ContentLanguage::current();

        $this->info("Reindexing with the '{$configuration}' text-search configuration.");

        // No chunking: this is a site of tens to hundreds of pages, and one
        // statement means one transaction, so a failure leaves the old index
        // intact rather than half of a new one.
        $count = DB::update('update pages set title = title');

        $this->info($count === 1 ? 'Reindexed 1 page.' : "Reindexed {$count} pages.");

        return self::SUCCESS;
    }
}
