<?php

namespace App\Observers;

use App\Models\Topic;
use App\Support\SlugRedirectRecorder;

class TopicObserver
{
    public function updating(Topic $topic): void
    {
        if ($topic->isDirty('slug') || $topic->isDirty('parent_id')) {
            SlugRedirectRecorder::recordTopicMove($topic);
        }
    }
}
