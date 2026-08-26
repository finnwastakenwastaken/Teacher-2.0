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
        'created' => 'Onderwerp aangemaakt.',
        'updated' => 'Onderwerp bijgewerkt.',
        'deleted' => 'Onderwerp verwijderd.',
        'has_children' => 'Dit onderwerp heeft nog subonderwerpen. Verplaats of verwijder deze eerst.',
        'has_pages' => 'Dit onderwerp heeft nog pagina\'s. Verplaats of verwijder deze eerst.',
        'max_depth' => 'Onderwerpen kunnen maximaal 3 niveaus diep zijn.',
        'own_parent' => 'Een onderwerp kan niet zijn eigen bovenliggende onderwerp zijn.',
        'own_descendant' => 'Een onderwerp kan niet onder zichzelf of onder een van zijn eigen subonderwerpen komen te staan.',
        'parent_missing' => 'Het gekozen bovenliggende onderwerp bestaat niet.',
        'intro_unreadable' => 'De inleiding van dit onderwerp kon niet worden gelezen.',
    ],

    'pages' => [
        'created' => 'Pagina aangemaakt.',
        'updated' => 'Pagina bijgewerkt.',
        'deleted' => 'Pagina verwijderd.',
        'duplicated' => 'Pagina gekopieerd. De kopie staat op verborgen tot je hem publiceert.',
        'content_saved' => 'Pagina-inhoud opgeslagen.',
        'content_unreadable' => 'De inhoud van deze pagina kon niet worden gelezen.',
        'draft_discarded' => 'Het concept is weggegooid. De gepubliceerde pagina is niet gewijzigd.',
        // Herstellen is zelf een publicatie: de versie die op de site stond
        // gaat als nieuwste versie de geschiedenis in, dus de stap is terug
        // te draaien.
        'revision_restored' => 'De oude versie staat weer op de site. De versie die er stond is bewaard in de geschiedenis.',
        'save_failed' => 'Deze wijziging kon niet worden opgeslagen.',
        'topic_required' => 'Kies een onderwerp voor deze pagina.',
    ],

    'downloads' => [
        'added' => 'Download toegevoegd.',
        'updated' => 'Download bijgewerkt.',
        'deleted' => 'Download verwijderd.',
        'file_required' => 'Kies een bestand.',
        // An attachment names one library or the other, never both — the same
        // rule the CHECK constraint on page_downloads enforces.
        'one_source_only' => 'Kies één bestand: een document of video, óf een afbeelding.',
        'file_missing' => 'Het gekozen bestand bestaat niet.',
        'already_attached' => 'Dit bestand staat al bij de downloads van deze pagina.',
        // The heading over the list of pages blocking a file's deletion.
        'offered_on' => 'Aangeboden als download op',
    ],

    'levels' => [
        'created' => 'Niveau toegevoegd.',
        'updated' => 'Niveau bijgewerkt.',
        'deleted' => 'Niveau verwijderd.',
        'merge_target_missing' => 'Het gekozen niveau om samen te voegen bestaat niet.',
        'in_use' => 'Dit niveau is nog gekoppeld aan :count download(s) en kan niet worden verwijderd. Voeg het eerst samen met een ander niveau.',
        'name_required' => 'Vul een naam in.',
        'slug_required' => 'De naam moet minstens één letter of cijfer bevatten.',
        'name_taken' => 'Er bestaat al een niveau met deze naam.',
    ],

    'passwords' => [
        'created' => 'Wachtwoord toegevoegd.',
        'changed' => 'Wachtwoord gewijzigd. Iedereen moet het opnieuw invoeren.',
        'updated' => 'Wachtwoord bijgewerkt.',
        'deleted' => 'Wachtwoord verwijderd.',
        'name_required' => 'Vul een naam in.',
        'name_taken' => 'Er bestaat al een wachtwoord met deze naam.',
        'password_required' => 'Vul een wachtwoord in.',
        'password_min' => 'Het wachtwoord moet minstens :count tekens lang zijn.',
    ],

    'media' => [
        'alt_required' => 'Alt-tekst is verplicht bij elke afbeelding.',
        'image_updated' => 'Afbeelding bijgewerkt.',
        'image_deleted' => 'Afbeelding verwijderd.',
        'file_deleted' => 'Bestand verwijderd.',
    ],

    'settings' => [
        'saved' => 'Instellingen opgeslagen.',
        'title_required' => 'Geef de site een titel.',
        'heading_required' => 'Geef de homepage een kop.',
        'content_unreadable' => 'De inhoud van de homepage kon niet worden gelezen.',
        'image_missing' => 'Die afbeelding bestaat niet.',
        'saved_and_reindexed' => 'Instellingen opgeslagen. De zoekindex is opnieuw opgebouwd voor de nieuwe taal.',
        'content_language_unknown' => 'Kies een taal uit de lijst.',
    ],

    'theme' => [
        'saved' => 'De kleuren zijn opgeslagen.',
        'reset' => 'De kleuren staan weer op de standaardinstelling.',
        // The contrast gate runs in the browser; this is the other check, and
        // it is the one that matters for safety: the value ends up inside a
        // <style> block, so anything that is not plainly a colour is refused
        // rather than repaired.
        'not_a_colour' => 'Dat is geen kleur. Gebruik een hexcode zoals #00a8ff.',
    ],

    'backups' => [
        'created' => 'Back-up gemaakt: :name',
        'deleted' => 'Back-up verwijderd.',
        'failed' => 'De back-up is niet gelukt: :reason',
    ],

    'profile' => [
        'updated' => 'Profiel bijgewerkt.',
    ],

    'security' => [
        'password_changed' => 'Wachtwoord gewijzigd. Je bent overal elders uitgelogd.',
    ],

    // Shared by topics and pages: both carry a title and a slug, and both
    // enforce sibling-unique slugs across the two tables at once.
    'fields' => [
        'title_required' => 'Vul een titel in.',
        'slug_required' => 'Vul een slug in.',
        'slug_format' => 'De slug mag alleen kleine letters, cijfers en koppeltekens bevatten.',
        'slug_taken' => 'Deze slug is al in gebruik binnen hetzelfde onderdeel.',
    ],

    'dependents' => [
        'file_in_use' => 'Dit bestand is nog in gebruik en kan niet worden verwijderd. :usages',
        'password_in_use' => 'Dit wachtwoord is nog in gebruik en kan niet worden verwijderd (:usages).',
        'used_on' => 'Gebruikt op',
        'banner_on' => 'Bannerafbeelding op',
        'used_by' => 'In gebruik bij',
        'site_settings' => 'Instellingen van de site',
        'topics' => 'onderwerpen',
        'pages' => 'pagina\'s',
    ],

    'sort' => [
        'unknown_group' => 'De volgorde kon niet worden opgeslagen: onbekend onderdeel.',
        'cross_group' => 'Onderdelen kunnen alleen binnen hetzelfde onderdeel worden gesorteerd.',
    ],

    // The checklist on the dashboard, which disappears once every step is
    // done. Read-only: each item links to the screen that actually does it.
    'dashboard' => [
        'steps' => [
            'branding' => [
                'title' => 'Geef de site een naam',
                'description' => 'Stel de naam, het logo en de favicon in.',
            ],
            'topic' => [
                'title' => 'Maak je eerste onderwerp',
                'description' => 'Onderwerpen vormen het menu en de indeling van de site.',
            ],
            'page' => [
                'title' => 'Maak je eerste pagina',
                'description' => 'Een pagina hangt onder een onderwerp en draagt de inhoud.',
            ],
            'content' => [
                'title' => 'Schrijf de inhoud van een pagina',
                'description' => 'Tekst, afbeeldingen, video en YouTube-fragmenten.',
            ],
            'media' => [
                'title' => 'Upload lesmateriaal',
                'description' => 'Afbeeldingen, documenten en video in de mediabibliotheek.',
            ],
            'download' => [
                'title' => 'Bied een download aan per niveau',
                'description' => 'Hetzelfde werkblad in een versie per leerweg.',
            ],
        ],
    ],

    // Only the ones that are words rather than names. "Lucide", "Tabler" and
    // "Material Design Icons" are what the projects call themselves.
    'icons' => [
        'tabler_filled' => 'Tabler (gevuld)',
    ],

];
