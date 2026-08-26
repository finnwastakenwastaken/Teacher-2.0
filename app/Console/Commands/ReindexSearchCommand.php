<?php

namespace App\Console\Commands;

use App\Support\ContentLanguage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-derive every page's search vector. The trigger only fires on write, so
 * changing the content language otherwise leaves existing rows stemmed by
 * the old rules — search quietly misses words rather than looking broken.
 * SiteSettingsController runs this automatically on setting change; it also
 * exists standalone for fixing an index after a backup restore or a
 * hand-edited row. `set title = title` touches a trigger-watched column so
 * Postgres recomputes the vector without repeating the trigger's SQL here.
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
