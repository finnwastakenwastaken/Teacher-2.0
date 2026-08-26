<?php

/*
 * Everything the front end says.
 *
 * Kept apart from admin.php and content.php — which the *server* uses — for
 * one practical reason: App\Support\Locale ships `common` and `ui` to the
 * browser and nothing else, so a group added for the server can never start
 * riding along on every document by accident.
 *
 * Nothing the owner writes belongs here. Page and topic titles, download
 * labels, education-level names and the site's own name are content, stored
 * once in whichever language they were written in.
 *
 * LocalisationTest scans resources/js for t('…') and asserts every key exists
 * in both locales, so a typo fails the build rather than putting a dotted key
 * path on screen.
 */

return [

    // Words that appear on more screens than they belong to.
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'add' => 'Add',
        'close' => 'Close',
        'back' => 'Back',
        'search' => 'Search',
        'choose' => 'Choose',
        'remove' => 'Remove',
        'confirm' => 'Confirm',
        'confirm_unavailable' => 'The confirmation could not be shown. Nothing was changed.',
        'copy' => 'Duplicate',
        'download' => 'Download',
        'upload' => 'Upload',
        'saving' => 'Saving…',
    ],

    'auth' => [
        'email' => 'Email address',
        'email_placeholder' => 'name@school.org',
        'password' => 'Password',
        'name' => 'Name',
        'full_name' => 'Full name',

        'login' => [
            'title' => 'Sign in',
            'description' => 'Enter your email address and password to continue',
            'remember' => 'Stay signed in',
            'submit' => 'Sign in',
        ],

        'claim' => [
            'title' => 'Create account',
            'description' => 'Create the one administrator account for this site',
            'confirm_password' => 'Confirm password',
            'setup_token' => 'Setup code',
            'submit' => 'Create account',
        ],

        'confirm' => [
            'title' => 'Confirm password',
            'description' => 'This is a protected area. Confirm your password to continue.',
            'passkey' => 'Confirm with a passkey',
            'working' => 'Working…',
            'separator' => 'Or confirm with your password',
            'submit' => 'Confirm password',
        ],

        'two_factor' => [
            'title' => 'Two-factor authentication',
            'code_title' => 'Sign-in code',
            'code_description' => 'Enter the code from your authenticator app.',
            'code_toggle' => 'sign in with a recovery code',
            'recovery_title' => 'Recovery code',
            'recovery_description' => 'Enter one of your recovery codes to confirm it is you.',
            'recovery_toggle' => 'sign in with a code from the app',
            'recovery_placeholder' => 'Recovery code',
            'submit' => 'Continue',
            'or' => 'or ',
        ],

        'requirements' => [
            'length' => 'At least :count characters long',
            'letter' => 'At least one letter',
            'mixed_case' => 'An uppercase and a lowercase letter',
            'number' => 'At least one number',
            'symbol' => 'At least one symbol, for example ! ? @ or #',
            'met' => '— met',
            'unmet' => '— the password does not meet this yet',
            'breach_check' => 'When you save, the password is checked against known data breaches. The password itself does not leave this server.',
            'show' => 'Show password',
            'hide' => 'Hide password',
        ],
    ],

    'nav' => [
        'admin' => 'Admin',
        'dashboard' => 'Dashboard',
        'content' => 'Content',
        'media' => 'Media',
        'levels' => 'Levels',
        'passwords' => 'Passwords',
        'backups' => 'Backups',
        'settings' => 'Settings',
        'theme' => 'Colours',
        'view_site' => 'View the website',
        'profile' => 'Profile',
        'security' => 'Security',
        'appearance' => 'Appearance',
        'sign_out' => 'Sign out',
    ],

    // dnd-kit's own announcements are English and name items by id
    // ("Draggable item 5 was dropped over droppable area 3"), which is no use
    // to the one person who will ever hear them. These name the item instead.
    'sortable' => [
        'instructions' => 'Press space or enter to pick this item up. Then use the up and down arrow keys to move it, space or enter to drop it, and escape to cancel.',
        'unnamed' => 'Item',
        'picked_up' => ':title picked up, position :position of :total.',
        'moved_over' => ':title is now at position :position of :total.',
        'dropped' => ':title dropped at position :position of :total.',
        'returned' => ':title returned to its previous place.',
        'cancelled' => 'Moving :title cancelled.',
        'handle' => 'Move: :title',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'subtitle' => 'An overview of your site.',
        'next_steps' => 'Getting started',
        // The count decides the form; `|` is Laravel's own choice syntax and
        // lib/i18n.ts reads it the same way.
        'remaining' => '1 step to go. You can do them in any order.|:count steps to go. You can do them in any order.',
        'recent' => 'Recently edited',
        'no_pages' => 'There are no pages yet.',
        'hidden' => 'Hidden',
        'empty' => 'No content yet',
        'has_draft' => 'Draft',

        // Its own block on the dashboard rather than a seventh item in the
        // "next steps" checklist: a step is done once and the list then
        // disappears, while a concept comes and goes for the life of the site.
        'drafts' => [
            'heading' => 'Unpublished drafts',
            'description' => '1 page is holding writing visitors cannot see yet. Open it to publish the draft or throw it away.|:count pages are holding writing visitors cannot see yet. Open one to publish the draft or throw it away.',
            'saved_at' => 'draft from :time',
        ],

        'popular' => 'Most downloaded',
        'counts_only' => 'Counts only. Nothing about visitors is recorded.',
        'nothing_fetched' => 'Nothing downloaded yet.',
        'topics' => 'Topics',
        'topics_hidden' => ':count hidden',
        'topics_all_visible' => 'All visible',
        'pages' => 'Pages',
        'pages_empty' => ':count still without content',
        'pages_hidden' => ':count hidden',
        'media' => 'Media',
        'media_in_use' => ':size in use',
        'media_none' => 'Nothing uploaded yet',
        'downloads' => 'Downloads',
        'downloads_served' => 'downloaded :count×',
        'levels' => '1 level|:count levels',
        'passwords' => '1 password|:count passwords',
    ],

    'content' => [
        'title' => 'Content',
        'hidden' => 'Hidden',
        // Beside the "hidden" badge rather than instead of it: a page can be
        // published and still be holding an unpublished concept.
        'has_draft' => 'Draft waiting',
        'empty' => 'No topics have been created yet.',
        'top_level' => 'Top-level topics',
        'duplicate' => 'Duplicate',
        'edit_title' => 'Editing ":title"',
        'confirm_delete_title' => 'Delete permanently?',
        'confirm_delete' => 'Are you sure you want to delete ":title"? This cannot be undone.',

        'topic' => [
            'new' => 'New topic',
            'edit' => 'Edit topic',
            'add_top_level' => '+ New top-level topic',
            'add_child' => '+ Subtopic',
            'children_of' => 'Subtopics of :title',
        ],

        'page' => [
            'new' => 'New page',
            'edit' => 'Edit page',
            'add' => '+ Page',
            'under_topic' => 'Pages under :title',
            'body_heading' => 'Content',
            'body_description' => 'The text, images, files and videos on this page. Do not forget to click "Save and publish".',
            'downloads_heading' => 'Downloads',
            'downloads_description' => 'Files at the bottom of the page, grouped by level. Every change is saved immediately.',
        ],
    ],

    // Shared by the topic and page forms, which differ in little more than
    // their placeholders and what an inherited password means.
    'forms' => [
        'title' => 'Title',
        'slug' => 'Slug',
        'icon' => 'Icon',
        'description' => 'Description',
        'description_placeholder' => 'Optional short description',
        'text' => 'Text',
        'hidden' => 'Hidden — does not appear in the menu or on the homepage, but stays reachable through a direct link',
        'topic_title_placeholder' => 'For example: Chapter 1',
        'topic_slug_placeholder' => 'for-example-chapter-1',
        'parent' => 'Parent topic',
        'no_parent' => 'None (top-level topic)',
        'topic_text_hint' => 'Optional. Appears above the list of subtopics and pages. Files and videos belong on a page, not here.',
        'topic_password_hint' => 'Protects this topic and everything below it. A page or subtopic with a password of its own takes precedence.',
        'page_title_placeholder' => 'For example: Lesson 1',
        'page_slug_placeholder' => 'for-example-lesson-1',
        'topic' => 'Topic',
        'topic_placeholder' => 'Choose a topic',
        'banner' => 'Banner image',
        'banner_hint' => 'Wide image at the top of the page, above the title. Optional.',
        'page_password_hint' => 'Without a password of its own, the nearest parent topic\'s password applies.',
    ],

    'downloads' => [
        'no_levels' => 'There are no levels yet. Add them under Levels.',
        'everyone' => 'For everybody',
        'fetched' => 'downloaded :count×',
        'close' => 'Close',
        'label_field' => 'Name on the page',
        'order' => 'Order',
        'levels' => 'Levels',
        'levels_hint' => 'Leave everything unticked for a download meant for everybody.',
        'confirm_remove_title' => 'Remove this download?',
        'confirm_remove' => 'Remove ":name" from this page? The file itself stays in the media library.',
        'empty' => 'No downloads on this page yet.',
        'add_heading' => 'Add a download',
        'new_levels_hint' => 'Applies to whatever you add or upload below. Leave everything unticked for a download meant for everybody.',
        'upload_title' => 'Upload a new file',
        'upload_description' => 'Becomes a download on this page straight away, with the levels above. At most :size per file.',
        'library_empty' => 'There are no files in the media library yet.',
        'library_exhausted' => 'Every file in the media library is already on this page.',
        'choose_file' => 'Choose from the library',
        'dialog_description' => 'Choose a file or an image from the media library. Its name and levels can still be changed afterwards.',
        // Which library the dialog is showing. Either can be handed out: a
        // poster or a scanned worksheet is an image.
        'source_label' => 'Choose from',
        'source_files' => 'Documents and videos',
        'source_images' => 'Pictures',
        // The levels are ticked behind the dialog, so it names the groups this
        // download will end up in — "For everybody" when nothing is ticked.
        'dialog_levels' => 'This download will appear under: :names',
        'chosen_file' => 'Chosen file: :name',
        'label_placeholder' => 'Optional',
        'attach_failed' => 'The file was uploaded, but could not be attached to this page.',
        'attach_cancelled' => 'Attaching it to this page was interrupted. The file is in the media library.',
    ],

    'uploader' => [
        'status' => [
            'waiting' => 'Waiting',
            'uploading' => 'Working',
            'done' => 'Done',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ],
        'server_error' => 'The server answered with an error (:status).',
        'cancelled' => 'Upload cancelled.',
        'failed' => 'Something went wrong during the upload.',
        'uploaded' => '1 file uploaded.|:count files uploaded.',
        'too_large' => '":name" is too large (:size). The maximum is :max.',
        'empty_file' => '":name" is empty and is being skipped.',
        'alt_required' => 'Alt text is required on every image.',
        'drop_here' => 'Drag files here',
        'drop_hint' => 'Or pick them yourself. At most :size per file. Large files are uploaded in parts.',
        'choose_files' => 'Choose files',
        'queue_heading' => 'Uploads',
        'clear_list' => 'Clear the list',
        'chunk_progress' => 'part :index of :total',
        'cancel_item' => 'Cancel the upload of :name',
        'alt_dialog_title' => 'Alt text for images',
        'alt_dialog_description' => 'Every image needs a short description, for screen readers and for when the image does not load. Without alt text the server refuses the upload.',
        'alt_others' => 'The other files you chose do not need alt text and are uploaded as they are.',
        'start' => 'Start uploading',

        // The extensions themselves come from App\Support\MediaFormats, which
        // reads the same table the server judges an upload against — so these
        // lines carry only the wording, never the list. A teacher who has to
        // upload a video to find out whether the format works is waiting on
        // gigabytes for the answer.
        'formats' => [
            'heading' => 'These file types are accepted:',
            'video' => 'Video: :list',
            'document' => 'Documents: :list',
            'image' => 'Images: :list',
        ],
    ],

    'library' => [
        'confirm_delete_title' => 'Delete from the media library?',
        'confirm_delete' => 'Are you sure you want to delete ":name"? This cannot be undone.',
        'edit_alt' => 'Edit alt text',
        'alt_required' => 'Alt text is required on every image.',
        'alt_save_failed' => 'The alt text could not be saved.',
        'no_images' => 'No images have been uploaded yet.',
        'alt_dialog_description' => 'Describe briefly what the image shows. Screen readers read this text out, and it appears when the image does not load.',
        'alt_label' => 'Alt text',
        'kind_document' => 'Document',
        'kind_video' => 'Video',
        // Shared by both file pickers, which show the same list of documents
        // and videos and differ only in what picking one does.
        'search' => 'Search by name',
        'search_placeholder' => 'For example: worksheet',
        'no_results' => 'No files found.',
        'preview' => 'Preview',
        'open' => 'Open',
        'no_files' => 'No documents or videos have been uploaded yet.',
        'video_preview_description' => 'The video is streamed with support for seeking.',
        // What an item is used for, derived from the rows that publish it and
        // never a flag the owner sets. Both can be true at once.
        'usage_shown' => 'On a page',
        'usage_download' => 'As a download',
        'usage_unused' => 'Not used anywhere',
        'filter_label' => 'Show',
        'filter_all' => 'Everything',
        // Shown by the picker dialogs when a search returns more matches than
        // fit in one page of results — same idea as icons.capped below.
        'capped' => ':count files, refine your search',
    ],

    'icons' => [
        'none_chosen' => 'No icon chosen',
        'dialog_title' => 'Choose an icon',
        'search_placeholder' => 'Search for an icon…',
        'filter_label' => 'Filter by collection',
        'all' => 'All',
        'none' => 'No icon',
        'no_results' => 'No icons found.',
        'capped' => ':count icons, narrow your search',
    ],

    'editor' => [
        'aria_label' => 'Page content',
        'placeholder' => 'Write the content of this page here…',
        'unsaved' => 'There are unsaved changes.',
        'saved' => 'All changes have been saved.',
        'save' => 'Save and publish',

        // The draft: a body that has been kept but is not on the site yet.
        // Deliberately not called "hidden" — a hidden page is a finished page
        // that is merely absent from the menu, and anyone with the link can
        // still read it.
        'draft' => [
            'save' => 'Save draft',
            'saving' => 'Saving draft…',
            'saved_at' => 'Draft kept at :time.',
            'failed' => 'The draft could not be kept. Use "Save and publish" to secure your work.',
            'unpublished' => 'This draft is not on the site yet.',
            'editing_heading' => 'You are editing an unpublished draft',
            'editing_description' => 'Kept at :time. Visitors still see the published version until you use "Save and publish".',
            'revert' => 'Back to the published version',
            'revert_title' => 'Discard this draft?',
            'revert_description' => 'The editor returns to the version that is on the site now. Everything written in this draft is lost.',
            'revert_confirm' => 'Discard draft',
        ],

        // The version history: the last ten published bodies of this page. A
        // draft does not count — it is kept every few seconds while you type,
        // and ten of those are ten seconds of one sentence.
        'history' => [
            'title' => 'Version history',
            'show' => 'Show version history',
            'hide' => 'Hide version history',
            'description' => 'The last ten versions that have been on the site. The one that is on it now is not in this list.',
            'empty' => 'There is no older version yet. The next time you publish this page, what is on it now will be kept here.',
            'version' => 'Version of :time',
            'view' => 'View',
            'failed' => 'This version could not be loaded.',
            'preview_empty' => 'This version was empty.',
            'close' => 'Close preview',
            'restore' => 'Restore',
            'restore_title' => 'Restore this version?',
            'restore_description' => 'The version of :time goes back on the site. What is on it now is kept as the newest version, so this can be undone.',
            // Restoring goes through the same publish as "Save and publish",
            // and that discards the draft. It must not happen silently: the
            // draft exists nowhere else.
            'restore_description_with_draft' => 'The version of :time goes back on the site. What is on it now is kept as the newest version. Note: your unpublished draft is lost in the process.',
            'restore_confirm' => 'Restore version',
        ],
        'bold' => 'Bold',
        'italic' => 'Italic',
        // H₂O and m/s² are unwritable without these, so the examples stay in
        // the label rather than in a tooltip nobody opens.
        'subscript' => 'Subscript (H₂O)',
        'superscript' => 'Superscript (m/s²)',
        'heading' => 'Heading',
        'heading_2' => 'Heading 2',
        'heading_3' => 'Heading 3',
        'align_left' => 'Align left',
        'align_center' => 'Centre',
        'align_right' => 'Align right',
        'align_justify' => 'Justify',
        'bullet_list' => 'Bulleted list',
        'ordered_list' => 'Numbered list',
        'blockquote' => 'Quote',
        'link' => 'Link',
        'insert_file' => 'Insert a file',
        'insert_images' => 'Insert images',
        'insert_image_aside' => 'Image beside text',
        'insert_youtube' => 'Insert a YouTube video',
        'insert_tiktok' => 'Insert a TikTok',
        'insert_instagram' => 'Insert an Instagram reel',
        'insert_table' => 'Insert a table',
        'row_above' => 'Row above',
        'row_below' => 'Row below',
        'delete_row' => 'Delete row',
        'column_left' => 'Column left',
        'column_right' => 'Column right',
        'delete_column' => 'Delete column',
        'merge_cells' => 'Merge cells',
        'delete_table' => 'Delete table',
        'image_not_a_file' => '":name" is an image. Insert it with the "Insert images" button.',

        'link_dialog' => [
            'description' => 'An address on this site starts with a slash, for example /chapter-1/lesson-1.',
            'address' => 'Address',
            'placeholder' => 'https://example.com',
            'invalid' => 'Use an address starting with http://, https://, mailto: or /.',
            'remove' => 'Remove link',
        ],

        'youtube_dialog' => [
            'description' => 'Paste the link to the video. Only the video ID is stored, and the video is shown without tracking cookies.',
            'label' => 'YouTube link or video ID',
            'placeholder' => 'https://www.youtube.com/watch?v=...',
            'invalid' => 'That is not a valid YouTube link. Paste the full link or just the video ID.',
            'insert' => 'Insert',
        ],

        'social_dialog' => [
            'tiktok_description' => 'Paste the link to the TikTok. Only the video number is stored.',
            'tiktok_label' => 'TikTok link or video number',
            'tiktok_placeholder' => 'https://www.tiktok.com/@name/video/...',
            'tiktok_invalid' => 'That is not a valid TikTok link. Paste the full link from the address bar.',
            'instagram_description' => 'Paste the link to the reel or post. Only the code is stored.',
            'instagram_label' => 'Instagram link or code',
            'instagram_placeholder' => 'https://www.instagram.com/reel/...',
            'instagram_invalid' => 'That is not a valid Instagram link. Paste the full link from the address bar.',
            'hint' => 'A shortened share link will not work. Open the video first and copy the link from the address bar.',
            'insert' => 'Insert',
        ],

        'file_dialog' => [
            'description' => 'Choose a document or video from the media library. The file only becomes public once this page is saved.',
            'upload_title' => 'Upload a new file',
            'upload_description' => 'Goes straight into the page at this spot.',
            'added' => '1 file added to the page.|:count files added to the page.',
            'remember_to_save' => 'Do not forget to click "Save and publish".',
            'empty' => 'There are no documents or videos yet. Upload one above, or under Media.',
        ],

        'image_dialog' => [
            'description' => 'Choose one or more images. They are inserted as a single gallery block, in the order you click them.',
            // Where the phone behaviour is stated. The block is arranged on a
            // wide screen, so otherwise nobody ever sees it stack.
            'description_aside' => 'Choose one image. The text below it runs alongside. On a phone the image sits above the text — there is no room beside it there.',
            'upload_title' => 'Upload a new image',
            'upload_description' => 'Goes into the media library and is ticked here straight away.',
            'not_an_image' => '":name" is not an image. Insert it with the "Insert a file" button.',
            'search' => 'Search',
            'search_placeholder' => 'Filename or alt text',
            'empty' => 'There are no images yet. Upload one above, or under Media.',
            'no_results' => 'No images found.',
            'insert' => 'Insert',
            'insert_count' => 'Insert :count',
        ],

        'blocks' => [
            'file_missing' => 'This file no longer exists. Delete this block.',
            'download_block' => 'Download block',
            'images_missing' => 'These images no longer exist. Delete this block.',
            'image_count' => '1 image|:count images',
            'youtube_invalid' => 'Invalid YouTube video. Delete this block.',
            'social_invalid' => 'Invalid TikTok or Instagram reel. Delete this block.',
            'social_open' => 'View the original post',
            'instagram_preview' => 'Instagram shows no video here. Students see a card linking to the post.',
            'aside_missing' => 'This image no longer exists. Delete this block.',
            'aside_left' => 'Left of the text',
            'aside_right' => 'Right of the text',
            'aside_small' => 'Small',
            'aside_medium' => 'Medium',
            'aside_large' => 'Large',
        ],
    ],

    'password_field' => [
        'label' => 'Password',
        'none' => 'No password',
        'empty' => 'There are no passwords yet. Create one under Passwords first.',
    ],

    'image_field' => [
        'none' => 'No image',
        'choose' => 'Choose',
        'replace' => 'Replace',
        // The buttons repeat the field's name because three "Choose" buttons
        // on the settings screen are indistinguishable to a screen reader.
        'choose_label' => ':field: choose',
        'replace_label' => ':field: replace',
        'remove_label' => ':field: remove',
        'dialog_description' => 'Choose an image from the media library.',
        'search_placeholder' => 'Search by filename or alt text',
        'search_label' => 'Search for an image',
        'empty' => 'The media library does not contain any images yet.',
        'no_results' => 'Nothing found.',
    ],

    'levels' => [
        'title' => 'Levels',
        'description' => 'What you tag downloads with. Students see the downloads grouped by level.',
        'name' => 'Name',
        'new' => 'New level',
        'name_placeholder' => 'For example: Foundation',
        'empty' => 'There are no levels yet.',
        // Split so the level's own name can be emphasised in the markup.
        'merge_intro_after' => 'is still attached to :count download(s). Choose which level those downloads move to.',
        'merge_into' => 'Merge into',
        'merge_placeholder' => 'Choose a level',
        'merge_confirm' => 'Merge and delete',
        'confirm_delete_title' => 'Delete this level?',
        'confirm_delete' => 'Delete the level ":name"? This only works while no download is still tagged with it.',
        'download_count' => ':count download(s)',
        'not_in_use' => 'not in use',
    ],

    'passwords' => [
        'title' => 'Passwords',
        'description' => 'Set a password on a topic or a page. Anyone who enters it can open everything protected by the same password. The name is visible to students, so do not put anything sensitive in it.',
        'name' => 'Name',
        'name_placeholder' => 'For example: Year 11 Foundation',
        'password' => 'Password',
        'new_password' => 'New password',
        'keep_placeholder' => 'Leave empty to keep',
        'change_warning' => 'If you change the password, everyone who had already entered it will have to enter it again.',
        'in_use' => 'Remove this password from the topics and pages using it first.',
        'empty' => 'There are no passwords yet.',
        'confirm_delete_title' => 'Delete this password?',
        'confirm_delete' => 'Delete the password ":name"? This only works while no topic or page still uses it.',
        'topic_count' => ':count topic(s)',
        'page_count' => ':count page(s)',
        'not_in_use' => 'not in use',
    ],

    'backups' => [
        'title' => 'Backups',
        'description' => 'A backup contains everything: the text, the structure, the settings and every file you have uploaded. With one such file you can set the site up again on another server.',
        'create' => 'Make a backup now',
        'creating' => 'Working… with a lot of files this takes a few minutes. Leave this screen open.',
        'may_take_a_while' => 'With a lot of files this takes a few minutes.',
        'offsite_title' => 'Keep a backup somewhere else.',
        'offsite_body' => 'While the file only exists on this server, you lose it along with the server. Download it and keep it on your laptop or an external drive.',
        'empty' => 'No backups have been made yet.',
        'restore_title' => 'Restoring a backup',
        // Split around the two <code> spans, which cannot be inside a string.
        'restore_body_1' => 'That happens on the server itself, not here — it erases everything currently there, and that is not a button anybody should be able to press by accident. The steps are in ',
        'restore_body_2' => '. In short: put the file on the server and run ',
        'restore_body_3' => '.',
        // The documentation is one English wiki, so this citation is the same
        // in both locales — see the allow-list in LocalisationTest.
        'restore_doc' => 'github.com/finnwastakenwastaken/Teacher-2.0/wiki/Backups-and-Restore',
        'restore_command' => './restore.sh <file>',
        'confirm_delete_title' => 'Delete this backup?',
        'confirm_delete' => 'Delete the backup from :moment? This cannot be undone.',
        'keep' => 'By default this server keeps the :count most recent backups when it prunes automatically.',
    ],

    'media' => [
        'title' => 'Media',
        'description' => 'Images, documents and videos you can use in pages.',
        'images' => 'Images',
        'files' => 'Files',
        'empty' => 'Nothing uploaded yet.',
        'image_count' => '1 image.|:count images.',
        'file_count' => '1 file (documents and videos).|:count files (documents and videos).',
    ],

    'site' => [
        'title' => 'Settings',
        'description' => 'The name and logo of the site, and the text at the top of the homepage.',
        'section_site' => 'The site',
        'name' => 'Name of the site',
        'name_hint' => 'Appears in the browser title bar and next to the logo.',
        'logo' => 'Logo',
        'logo_hint' => 'Appears in the top left of every page. Leave empty for the name alone.',
        'favicon' => 'Favicon',
        'favicon_hint' => 'The small icon in the browser tab. A square 32 by 32 pixel PNG works best.',
        'section_home' => 'Homepage',
        'home_hint' => 'Everything below sits at the top of the homepage. The tiles of top-level topics are always underneath and cannot be removed.',
        'heading' => 'Heading',
        'subheading' => 'Subheading',
        'banner' => 'Banner',
        'banner_hint' => 'Wide image at the top of the homepage.',
        'text' => 'Text',
        'text_hint' => 'Optional. Files and videos belong on a page, not here.',
        'section_privacy' => 'Privacy page',
        'privacy_text' => 'Your own addition',
        'privacy_text_hint' => 'Optional. What the site itself records is already stated; this is for what only you know, such as who students can go to with a question.',

        'section_search' => 'Search',
        'content_language' => 'Language of your material',
        'content_language_hint' => 'Decides how the search recognises words, so that "forces" also finds "force". This is about the language you write in, not the language of the buttons — every visitor chooses that themselves. Changing it rebuilds the search index straight away.',
        'content_language_dutch' => 'Dutch',
        'content_language_english' => 'English',
    ],

    'theme' => [
        'title' => 'Colours',
        'description' => 'This is how you give the site colours of your own. You edit the base palette; buttons, links, messages and the sidebar are derived from it and follow along — in the light theme and the dark one.',
        'section_palette' => 'Base palette',
        'section_preview' => 'Preview',
        'section_contrast' => 'Readability',
        'pick' => 'Choose :colour',
        'reset_one' => 'Reset :colour',
        'reset_all' => 'Reset everything',
        'theme_light' => 'light theme',
        'theme_dark' => 'dark theme',
        'preview_heading' => 'This is what a page looks like',
        'preview_body' => 'Ordinary text, with a quieter line underneath it.',
        'preview_card' => 'A block of content',
        'preview_link' => 'A link to another page',
        'preview_button' => 'Button',
        'preview_success' => 'Done',
        'preview_destructive' => 'Delete',
        'contrast_ok' => 'All :count combinations of text and background reach :ratio:1 or better, in both themes.',
        'contrast_failed' => 'These colours leave the site hard to read',
        'contrast_failed_hint' => 'Saving is blocked until everything meets the WCAG AA readability standard again. Reset the colour, or pick a clearly darker or lighter variant.',
        'contrast_fail' => ':pair reaches :ratio:1 in the :theme, and it has to be at least :minimum:1.',
        'contrast_unreadable' => ':pair could not be measured in the :theme.',
        'blocked' => 'Sort the readability out first.',

        // The raw palette, in the order resources/css/app.css declares it.
        // These are names of colours, not of the roles built on them: the
        // roles are derived in CSS and are deliberately not editable here.
        'colours' => [
            'blue' => 'Blue',
            'blue-deep' => 'Deep blue',
            'purple' => 'Purple',
            'purple-deep' => 'Deep purple',
            'yellow' => 'Yellow',
            'yellow-deep' => 'Deep yellow',
            'green' => 'Green',
            'green-deep' => 'Deep green',
            'steel' => 'Steel blue',
            'steel-deep' => 'Deep steel blue',
            'red' => 'Red',
            'red-deep' => 'Deep red',
            'grey-50' => 'Grey 50 (page colour)',
            'grey-100' => 'Grey 100 (borders)',
            'grey-400' => 'Grey 400',
            'grey-500' => 'Grey 500',
            'navy' => 'Navy',
            'navy-deep' => 'Deep navy',
            'slate' => 'Slate',
            'slate-deep' => 'Deep slate (dark page)',
            'white' => 'White (cards)',
        ],

        // The combinations that are measured. Described as what the reader
        // actually sees, not as the name of the token — nobody outside this
        // repository knows what "sidebar-accent-foreground" is.
        'pairs' => [
            'page' => 'Text on the page',
            'card' => 'Text on a card',
            'popover' => 'Text in a pop-up menu',
            'primary' => 'The label on a primary button',
            'secondary' => 'The label on a secondary button',
            'muted' => 'Text on a grey panel',
            'accent' => 'Text on a highlighted menu item',
            'destructive' => 'The label on a delete button',
            'success' => 'The label on a success message',
            'warning' => 'The label on a warning',
            'info' => 'The label on an information message',
            'sidebar' => 'Text in the sidebar',
            'sidebar_primary' => 'The label on the sidebar button',
            'sidebar_accent' => 'Text on the active sidebar item',
            'link_on_page' => 'A link on the page',
            'link_on_card' => 'A link on a card',
            'error_on_page' => 'An error message on the page',
            'error_on_card' => 'An error message on a card',
            'muted_on_page' => 'Quiet text on the page',
            'muted_on_card' => 'Quiet text on a card',
        ],
    ],

    'settings' => [
        'profile' => [
            'page_title' => 'Profile settings',
            'title' => 'Profile',
            'description' => 'Change your name and email address',
            'saved' => 'Saved',
        ],

        'appearance' => [
            'title' => 'Appearance',
            'description' => 'Choose whether the site is shown light or dark. This choice applies on this device only.',

            // The three-way choice lives here and only here. The public
            // header carries a two-state toggle instead, because a control
            // that cycles three states never says what the next press does.
            'light' => 'Light',
            'dark' => 'Dark',
            'system' => 'System',
        ],

        'security' => [
            'title' => 'Security',
            'password_title' => 'Change password',
            'password_description' => 'Use a long, unique password. If you lose it, the admin:reset-password command on the server is the only way back.',
            'current_password' => 'Current password',
            'new_password' => 'New password',
            'repeat_password' => 'Repeat new password',
        ],

        'two_factor' => [
            'title' => 'Two-factor authentication',
            'description' => 'An extra code when signing in, from an authenticator app on your phone.',
            'enabled_explanation' => 'When signing in you are asked for a code from the authenticator app on your phone. The code changes every thirty seconds.',
            'disabled_explanation' => 'Turn this on and the site will ask for a code from an authenticator app on your phone alongside your password. Keep the recovery codes you are given afterwards somewhere safe.',
            'disable' => 'Turn off two-factor authentication',
            'enable' => 'Turn on two-factor authentication',
            'finish_setup' => 'Finish setting up',
            'enabled_heading' => 'Two-factor authentication is on',
            'scan_or_key' => 'Scan the QR code with your authenticator app, or enter the key by hand.',
            'scan_or_key_short' => 'Scan the QR code with your authenticator app, or enter the key by hand',
            'verify_heading' => 'Check the code',
            'verify_description' => 'Enter the six-digit code from your authenticator app',
            'continue' => 'Continue',
        ],

        'recovery' => [
            'title' => 'Recovery codes',
            'description' => 'A recovery code gets you in if you lose your phone. Keep them in a password manager.',
            'show' => 'Show recovery codes',
            'hide' => 'Hide recovery codes',
            'loading' => 'Loading recovery codes',
            'regenerate' => 'Generate new codes',
            // Split around the button's name so the sentence can put it
            // wherever the language wants it, in bold.
            'single_use_before' => 'Each recovery code works once and then expires. If you need more, click ',
            'single_use_after' => ' above.',
        ],

        'passkeys' => [
            'title' => 'Passkeys',
            'description' => 'Sign in without a password, using your device\'s fingerprint or PIN.',
            'none' => 'No passkeys yet',
            'none_hint' => 'A passkey lets you sign in without a password',
            'add' => 'Add passkey',
            'name_placeholder' => 'For example: school laptop, iPhone',
            'name_hint' => 'A name helps you recognise which device this is later.',
            'save' => 'Save passkey',
            'saving' => 'Working…',
            'delete' => 'Delete passkey',
            'delete_sr' => 'Delete',
            'delete_confirm' => 'Are you sure you want to delete the passkey :name? You will not be able to sign in with it afterwards.',
            'name_label' => 'Name of the passkey',
            'deleting' => 'Working…',
            'unsupported' => 'This browser does not support passkeys.',
            'signing_in' => 'Signing in…',
            'sign_in' => 'Sign in with a passkey',
            'separator' => 'Or continue with email',
        ],
    ],

    'public' => [
        'downloads' => [
            'heading' => 'Downloads',
            'my_level' => 'My level:',
            // The count decides the form; `|` is Laravel's own choice syntax
            // and lib/i18n.ts reads it the same way.
            'count' => '1 file|:count files',
        ],

        'locked' => [
            'named' => 'This page is protected. Enter the password for :name.',
            'unnamed' => 'This page is protected. Enter the password.',
            'password' => 'Password',
            'unlock' => 'Unlock',
        ],

        // What the software does with data — the application's own words, so
        // translated. Every sentence here has to match the code: an awkward
        // truth is better than a promise that cannot be kept.
        'privacy' => [
            'title' => 'Privacy and your data',
            'intro' => 'This site exists to share course material, not to follow the people who visit it. Here is what is and is not recorded.',

            'no_account_heading' => 'No account, no signing in',
            'no_account' => 'Students do not create an account and never sign in anywhere. There is no way to register on this site at all. Only the teacher who runs it has an account.',

            'no_tracking_heading' => 'No trackers, no visitor statistics',
            'no_tracking' => 'There is no analytics software, no advertising network and no tracking script on this site. No JavaScript from anybody else runs here.',

            'cookies_heading' => 'What stays in your own browser',
            'cookies' => 'The level you pick, your language and your choice of a light or dark appearance are kept in your own browser. The server keeps no record of them. If you enter the password for a protected page, your browser remembers that you know the password — not who you are.',

            'logs_heading' => 'What the server does keep',
            'logs' => 'As on almost every website, the web server keeps a log of the addresses requested, with an IP address and a time. That comes with running a server. It is not used to follow who looks at what.',

            'counter_heading' => 'The download counter',
            'counter' => 'Each file counts how often it has been fetched. That is one number per file. Who fetched it is not recorded, and two downloads cannot be tied back to the same person.',

            'video_heading' => 'Video from other sites',
            'video' => 'When a page carries a YouTube video, your browser contacts YouTube as soon as you open that page, and YouTube can see your IP address. That is inherent to embedding video and cannot be avoided. A TikTok video is not fetched until you press play yourself. Posts from Instagram are never loaded at all — there is only a link to the original.',

            'photos_heading' => 'Photographs',
            'photos' => 'Uploaded photographs have the hidden information a camera attaches stripped out, including where the picture was taken.',

            'owner_heading' => 'From the person who runs this site',
        ],

        'search' => [
            'title' => 'Search',
            'title_for' => 'Search for :query',
            'field' => 'Search term',
            'placeholder' => 'For example: summary',
            'none' => 'No results for “:query”.',
        ],

        'topic' => [
            'empty' => 'This section has no content yet.',
        ],

        // The button that enlarges a picture carries the picture and nothing
        // else, so its accessible name is the alt text plus this word — the
        // alt says what it is, this says what pressing it does.
        'lightbox' => [
            'enlarge' => 'Enlarge',
            'previous' => 'Previous image',
            'next' => 'Next image',
            'counter' => 'Image :current of :total',
        ],

        'page_empty' => 'This page has no content yet.',
        'nothing_published' => 'No course material has been published yet.',
        'video_unsupported' => 'Your browser cannot play this video.',
        'youtube_title' => 'YouTube video',
        'social_title' => ':platform video',
        'social_load' => 'Load video from :platform',
        'social_notice' => 'The video only loads once you click here. :platform can recognise you from then on.',
        'instagram_open' => 'View this post on Instagram',
        'instagram_notice' => 'Instagram only plays this video on their own site. The link opens in a new tab.',

        'header' => [
            'search' => 'Search',
            'admin' => 'Admin',

            // The light/dark button carries an icon and nothing else, so its
            // accessible name is the only thing that says what pressing it
            // does. Hence a verb and the theme it moves to, rather than the
            // name of the theme showing now.
            'appearance' => [
                'to_light' => 'Switch to the light theme',
                'to_dark' => 'Switch to the dark theme',
            ],
        ],
    ],

];
