<?php

namespace App\Observers;

use App\Models\Page;
use App\Support\SlugRedirectRecorder;

class PageObserver
{
    public function updating(Page $page): void
    {
        if ($page->isDirty('slug') || $page->isDirty('topic_id')) {
            SlugRedirectRecorder::recordPageMove($page);
        }
    }
}
