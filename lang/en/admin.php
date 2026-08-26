<?php

/*
 * The admin panel: flash messages, validation messages, and the few labels
 * the server decides rather than the front end.
 *
 * Nothing the owner writes belongs here. Titles, descriptions, download
 * labels and education-level names are content, stored once in whichever
 * language they were written in — see the top of the technical reference.
 */

return [

    'topics' => [
        'created' => 'Topic created.',
        'updated' => 'Topic updated.',
        'deleted' => 'Topic deleted.',
        'has_children' => 'This topic still has subtopics. Move or delete those first.',
        'has_pages' => 'This topic still has pages. Move or delete those first.',
        'max_depth' => 'Topics can be at most three levels deep.',
        'own_parent' => 'A topic cannot be its own parent.',
        'own_descendant' => 'A topic cannot be moved under itself or under one of its own subtopics.',
        'parent_missing' => 'That parent topic does not exist.',
        'intro_unreadable' => 'The introduction for this topic could not be read.',
    ],

    'pages' => [
        'created' => 'Page created.',
        'updated' => 'Page updated.',
        'deleted' => 'Page deleted.',
        'duplicated' => 'Page copied. The copy is hidden until you publish it.',
        'content_saved' => 'Page content saved.',
        'content_unreadable' => 'The content of this page could not be read.',
        'draft_discarded' => 'The draft has been discarded. The published page is unchanged.',
        // Restoring is itself a publish: the body that was on the site goes
        // into the history as its newest version, so the step can be undone.
        'revision_restored' => 'The older version is on the site again. The one it replaced is kept in the history.',
        'save_failed' => 'That change could not be saved.',
        'topic_required' => 'Choose a topic for this page.',
    ],

    'downloads' => [
        'added' => 'Download added.',
        'updated' => 'Download updated.',
        'deleted' => 'Download deleted.',
        'file_required' => 'Choose a file.',
        // An attachment names one library or the other, never both — the same
        // rule the CHECK constraint on page_downloads enforces.
        'one_source_only' => 'Choose one file: either a document or video, or an image.',
        'file_missing' => 'That file does not exist.',
        'already_attached' => 'This file is already among this page\'s downloads.',
        // The heading over the list of pages blocking a file's deletion.
        'offered_on' => 'Offered as a download on',
    ],

    'levels' => [
        'created' => 'Level added.',
        'updated' => 'Level updated.',
        'deleted' => 'Level deleted.',
        'merge_target_missing' => 'The level you chose to merge into does not exist.',
        'in_use' => 'This level is still attached to :count download(s) and cannot be deleted. Merge it into another level first.',
        'name_required' => 'Enter a name.',
        'slug_required' => 'The name must contain at least one letter or number.',
        'name_taken' => 'A level with this name already exists.',
    ],

    'passwords' => [
        'created' => 'Password added.',
        'changed' => 'Password changed. Everyone will have to enter it again.',
        'updated' => 'Password updated.',
        'deleted' => 'Password deleted.',
        'name_required' => 'Enter a name.',
        'name_taken' => 'A password with this name already exists.',
        'password_required' => 'Enter a password.',
        'password_min' => 'The password must be at least :count characters long.',
    ],

    'media' => [
        'alt_required' => 'Every image needs alternative text.',
        'image_updated' => 'Image updated.',
        'image_deleted' => 'Image deleted.',
        'file_deleted' => 'File deleted.',
    ],

    'settings' => [
        'saved' => 'Settings saved.',
        'title_required' => 'Give the site a title.',
        'heading_required' => 'Give the homepage a heading.',
        'content_unreadable' => 'The homepage content could not be read.',
        'image_missing' => 'That image does not exist.',
        'saved_and_reindexed' => 'Settings saved. The search index has been rebuilt for the new language.',
        'content_language_unknown' => 'Choose a language from the list.',
    ],

    'theme' => [
        'saved' => 'The colours have been saved.',
        'reset' => 'The colours are back to the ones the site ships with.',
        // The contrast gate runs in the browser; this is the other check, and
        // it is the one that matters for safety: the value ends up inside a
        // <style> block, so anything that is not plainly a colour is refused
        // rather than repaired.
        'not_a_colour' => 'That is not a colour. Use a hex code such as #00a8ff.',
    ],

    'backups' => [
        'created' => 'Backup made: :name',
        'deleted' => 'Backup deleted.',
        'failed' => 'The backup failed: :reason',
    ],

    'profile' => [
        'updated' => 'Profile updated.',
    ],

    'security' => [
        'password_changed' => 'Password changed. You have been signed out everywhere else.',
    ],

    // Shared by topics and pages: both carry a title and a slug, and both
    // enforce sibling-unique slugs across the two tables at once.
    'fields' => [
        'title_required' => 'Enter a title.',
        'slug_required' => 'Enter a slug.',
        'slug_format' => 'A slug may only contain lowercase letters, numbers and hyphens.',
        'slug_taken' => 'This slug is already in use within the same section.',
    ],

    'dependents' => [
        'file_in_use' => 'This file is still in use and cannot be deleted. :usages',
        'password_in_use' => 'This password is still in use and cannot be deleted (:usages).',
        'used_on' => 'Used on',
        'banner_on' => 'Banner image on',
        'used_by' => 'Used by',
        'site_settings' => 'The site settings',
        'topics' => 'topics',
        'pages' => 'pages',
    ],

    'sort' => [
        'unknown_group' => 'The order could not be saved: unknown section.',
        'cross_group' => 'Items can only be reordered within the same section.',
    ],

    // The checklist on the dashboard, which disappears once every step is
    // done. Read-only: each item links to the screen that actually does it.
    'dashboard' => [
        'steps' => [
            'branding' => [
                'title' => 'Give the site a name',
                'description' => 'Set the name, the logo and the favicon.',
            ],
            'topic' => [
                'title' => 'Create your first topic',
                'description' => 'Topics form the menu and the shape of the site.',
            ],
            'page' => [
                'title' => 'Create your first page',
                'description' => 'A page sits under a topic and carries the content.',
            ],
            'content' => [
                'title' => 'Write the content of a page',
                'description' => 'Text, images, video and YouTube clips.',
            ],
            'media' => [
                'title' => 'Upload course material',
                'description' => 'Images, documents and video in the media library.',
            ],
            'download' => [
                'title' => 'Offer a download per level',
                'description' => 'The same worksheet in a version for each track.',
            ],
        ],
    ],

    // Only the ones that are words rather than names. "Lucide", "Tabler" and
    // "Material Design Icons" are what the projects call themselves.
    'icons' => [
        'tabler_filled' => 'Tabler (filled)',
    ],

];
