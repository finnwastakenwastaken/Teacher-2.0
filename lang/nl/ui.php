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
        'save' => 'Opslaan',
        'cancel' => 'Annuleren',
        'delete' => 'Verwijderen',
        'edit' => 'Bewerken',
        'add' => 'Toevoegen',
        'close' => 'Sluiten',
        'back' => 'Terug',
        'search' => 'Zoeken',
        'choose' => 'Kiezen',
        'remove' => 'Weghalen',
        'confirm' => 'Bevestigen',
        'copy' => 'Kopiëren',
        'download' => 'Downloaden',
        'upload' => 'Uploaden',
        'saving' => 'Bezig met opslaan…',
    ],

    'auth' => [
        'email' => 'E-mailadres',
        'email_placeholder' => 'naam@school.nl',
        'password' => 'Wachtwoord',
        'name' => 'Naam',
        'full_name' => 'Volledige naam',

        'login' => [
            'title' => 'Inloggen',
            'description' => 'Vul je e-mailadres en wachtwoord in om verder te gaan',
            'remember' => 'Aangemeld blijven',
            'submit' => 'Inloggen',
        ],

        'claim' => [
            'title' => 'Account aanmaken',
            'description' => 'Maak het enige beheerdersaccount van deze site aan',
            'confirm_password' => 'Bevestig wachtwoord',
            'setup_token' => 'Installatiecode',
            'submit' => 'Account aanmaken',
        ],

        'confirm' => [
            'title' => 'Wachtwoord bevestigen',
            'description' => 'Dit is een beveiligd onderdeel. Bevestig je wachtwoord om verder te gaan.',
            'passkey' => 'Bevestigen met een passkey',
            'working' => 'Bezig…',
            'separator' => 'Of bevestig met je wachtwoord',
            'submit' => 'Wachtwoord bevestigen',
        ],

        'two_factor' => [
            'title' => 'Tweestapsverificatie',
            'code_title' => 'Inlogcode',
            'code_description' => 'Vul de code in uit je authenticator-app.',
            'code_toggle' => 'inloggen met een herstelcode',
            'recovery_title' => 'Herstelcode',
            'recovery_description' => 'Vul een van je herstelcodes in om te bevestigen dat jij het bent.',
            'recovery_toggle' => 'inloggen met een code uit de app',
            'recovery_placeholder' => 'Herstelcode',
            'submit' => 'Doorgaan',
            'or' => 'of ',
        ],

        'requirements' => [
            'length' => 'Minstens :count tekens lang',
            'letter' => 'Minstens één letter',
            'mixed_case' => 'Een hoofdletter en een kleine letter',
            'number' => 'Minstens één cijfer',
            'symbol' => 'Minstens één leesteken, bijvoorbeeld ! ? @ of #',
            'met' => '— voldaan',
            'unmet' => '— hier voldoet het wachtwoord nog niet aan',
            'breach_check' => 'Het wachtwoord wordt bij het opslaan gecontroleerd tegen bekende datalekken. Het wachtwoord zelf verlaat deze server daarbij niet.',
            'show' => 'Wachtwoord tonen',
            'hide' => 'Wachtwoord verbergen',
        ],
    ],

    'nav' => [
        'admin' => 'Beheer',
        'dashboard' => 'Dashboard',
        'content' => 'Inhoud',
        'media' => 'Media',
        'levels' => 'Niveaus',
        'passwords' => 'Wachtwoorden',
        'backups' => 'Back-ups',
        'settings' => 'Instellingen',
        'view_site' => 'Bekijk de website',
        'profile' => 'Profiel',
        'security' => 'Beveiliging',
        'appearance' => 'Weergave',
        'sign_out' => 'Uitloggen',
    ],

    // dnd-kit's own announcements are English and name items by id
    // ("Draggable item 5 was dropped over droppable area 3"), which is no use
    // to the one person who will ever hear them. These name the item instead.
    'sortable' => [
        'instructions' => 'Druk op spatie of enter om dit onderdeel op te pakken. Gebruik daarna de pijltoetsen omhoog en omlaag om het te verplaatsen, spatie of enter om het neer te zetten, en escape om te annuleren.',
        'unnamed' => 'Onderdeel',
        'picked_up' => ':title opgepakt, plek :position van :total.',
        'moved_over' => ':title staat nu op plek :position van :total.',
        'dropped' => ':title neergezet op plek :position van :total.',
        'returned' => ':title teruggezet op zijn oude plek.',
        'cancelled' => 'Verplaatsen van :title geannuleerd.',
        'handle' => 'Verplaatsen: :title',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'subtitle' => 'Een overzicht van je site.',
        'next_steps' => 'Aan de slag',
        // The count decides the form; `|` is Laravel's own choice syntax and
        // lib/i18n.ts reads it the same way.
        'remaining' => 'Nog 1 stap te gaan. Je kunt hem in elke volgorde doen.|Nog :count stappen te gaan. Je kunt ze in elke volgorde doen.',
        'recent' => 'Onlangs bewerkt',
        'no_pages' => 'Er zijn nog geen pagina\'s.',
        'hidden' => 'Verborgen',
        'empty' => 'Nog geen inhoud',
        'popular' => 'Meest opgehaald',
        'counts_only' => 'Alleen aantallen. Er wordt niets over bezoekers vastgelegd.',
        'nothing_fetched' => 'Nog niets opgehaald.',
        'topics' => 'Onderwerpen',
        'topics_hidden' => ':count verborgen',
        'topics_all_visible' => 'Allemaal zichtbaar',
        'pages' => 'Pagina\'s',
        'pages_empty' => ':count nog zonder inhoud',
        'pages_hidden' => ':count verborgen',
        'media' => 'Media',
        'media_in_use' => ':size in gebruik',
        'media_none' => 'Nog niets geüpload',
        'downloads' => 'Downloads',
        'downloads_served' => ':count× opgehaald',
        'levels' => '1 niveau|:count niveaus',
        'passwords' => '1 wachtwoord|:count wachtwoorden',
    ],

    'content' => [
        'title' => 'Inhoud',
        'hidden' => 'Verborgen',
        'empty' => 'Er zijn nog geen onderwerpen aangemaakt.',
        'top_level' => 'Hoofdonderwerpen',
        'duplicate' => 'Dupliceren',
        'edit_title' => '":title" bewerken',
        'confirm_delete' => 'Weet je zeker dat je ":title" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.',

        'topic' => [
            'new' => 'Nieuw onderwerp',
            'edit' => 'Onderwerp bewerken',
            'add_top_level' => '+ Nieuw hoofdonderwerp',
            'add_child' => '+ Subonderwerp',
            'children_of' => 'Subonderwerpen van :title',
        ],

        'page' => [
            'new' => 'Nieuwe pagina',
            'edit' => 'Pagina bewerken',
            'add' => '+ Pagina',
            'under_topic' => 'Pagina’s onder :title',
            'body_heading' => 'Inhoud',
            'body_description' => 'De tekst, afbeeldingen, bestanden en video\'s op deze pagina. Vergeet niet op "Inhoud opslaan" te klikken.',
            'downloads_heading' => 'Downloads',
            'downloads_description' => 'Bestanden onderaan de pagina, gegroepeerd per niveau. Elke wijziging wordt meteen opgeslagen.',
        ],
    ],

    // Shared by the topic and page forms, which differ in little more than
    // their placeholders and what an inherited password means.
    'forms' => [
        'title' => 'Titel',
        'slug' => 'Slug',
        'icon' => 'Icoon',
        'description' => 'Omschrijving',
        'description_placeholder' => 'Optionele korte omschrijving',
        'text' => 'Tekst',
        'hidden' => 'Verborgen — verschijnt niet in het menu of op de homepage, maar blijft bereikbaar via een directe link',
        'topic_title_placeholder' => 'Bijv. Hoofdstuk 1',
        'topic_slug_placeholder' => 'bijv-hoofdstuk-1',
        'parent' => 'Bovenliggend onderwerp',
        'no_parent' => 'Geen (hoofdonderwerp)',
        'topic_text_hint' => 'Optioneel. Verschijnt boven de lijst met subonderwerpen en pagina’s. Bestanden en video’s horen op een pagina, niet hier.',
        'topic_password_hint' => 'Beveiligt dit onderwerp en alles wat eronder valt. Een pagina of subonderwerp met een eigen wachtwoord gaat voor.',
        'page_title_placeholder' => 'Bijv. Les 1',
        'page_slug_placeholder' => 'bijv-les-1',
        'topic' => 'Onderwerp',
        'topic_placeholder' => 'Kies een onderwerp',
        'banner' => 'Bannerafbeelding',
        'banner_hint' => 'Brede afbeelding bovenaan de pagina, boven de titel. Optioneel.',
        'page_password_hint' => 'Zonder eigen wachtwoord geldt dat van het dichtstbijzijnde bovenliggende onderwerp.',
    ],

    'downloads' => [
        'no_levels' => 'Er zijn nog geen niveaus. Voeg ze toe bij Niveaus.',
        'everyone' => 'Voor iedereen',
        'fetched' => ':count× gedownload',
        'close' => 'Sluiten',
        'label_field' => 'Naam op de pagina',
        'order' => 'Volgorde',
        'levels' => 'Niveaus',
        'levels_hint' => 'Laat alles leeg voor een download die voor iedereen bedoeld is.',
        'confirm_remove' => '":name" van deze pagina halen? Het bestand zelf blijft in de mediabibliotheek.',
        'empty' => 'Nog geen downloads op deze pagina.',
        'add_heading' => 'Download toevoegen',
        'new_levels_hint' => 'Geldt voor wat je hieronder toevoegt of uploadt. Laat alles leeg voor een download die voor iedereen bedoeld is.',
        'upload_title' => 'Nieuw bestand uploaden',
        'upload_description' => 'Komt meteen als download op deze pagina te staan, met de niveaus hierboven. Maximaal :size per bestand.',
        'library_empty' => 'Er staan nog geen bestanden in de mediabibliotheek.',
        'library_exhausted' => 'Alle bestanden uit de mediabibliotheek staan al op deze pagina.',
        'choose_file' => 'Bestand kiezen',
        'dialog_description' => 'Kies een document of video uit de mediabibliotheek. De naam en de niveaus kun je daarna nog aanpassen.',
        // The levels are ticked behind the dialog, so it names the groups this
        // download will end up in — "Voor iedereen" when nothing is ticked.
        'dialog_levels' => 'Deze download komt te staan onder: :names',
        'chosen_file' => 'Gekozen: :name',
        'label_placeholder' => 'Optioneel',
        // An image cannot be a download; the server decides by sniffing.
        'image_not_a_download' => '":name" is een afbeelding en staat nu in de mediabibliotheek. Downloads zijn documenten of video’s.',
        'attach_failed' => 'Het bestand is geüpload, maar kon niet aan deze pagina worden gekoppeld.',
        'attach_cancelled' => 'Het koppelen aan deze pagina is afgebroken. Het bestand staat wel in de mediabibliotheek.',
    ],

    'uploader' => [
        'status' => [
            'waiting' => 'Wachten',
            'uploading' => 'Bezig',
            'done' => 'Klaar',
            'failed' => 'Mislukt',
            'cancelled' => 'Geannuleerd',
        ],
        'server_error' => 'De server antwoordde met een fout (:status).',
        'cancelled' => 'Upload geannuleerd.',
        'failed' => 'Er ging iets mis tijdens het uploaden.',
        'uploaded' => '1 bestand geüpload.|:count bestanden geüpload.',
        'too_large' => '":name" is te groot (:size). Het maximum is :max.',
        'empty_file' => '":name" is leeg en wordt overgeslagen.',
        'alt_required' => 'Alt-tekst is verplicht bij elke afbeelding.',
        'drop_here' => 'Sleep bestanden hierheen',
        'drop_hint' => 'Of kies ze zelf. Maximaal :size per bestand. Grote bestanden worden in delen geüpload.',
        'choose_files' => 'Bestanden kiezen',
        'queue_heading' => 'Uploads',
        'clear_list' => 'Lijst wissen',
        'chunk_progress' => 'deel :index van :total',
        'cancel_item' => 'Upload van :name annuleren',
        'alt_dialog_title' => 'Alt-tekst voor afbeeldingen',
        'alt_dialog_description' => 'Elke afbeelding heeft een korte beschrijving nodig voor schermlezers en voor als de afbeelding niet laadt. Zonder alt-tekst weigert de server de upload.',
        'alt_others' => 'De overige gekozen bestanden hebben geen alt-tekst nodig en worden gewoon meegeüpload.',
        'start' => 'Uploaden starten',
    ],

    'library' => [
        'confirm_delete' => 'Weet je zeker dat je ":name" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.',
        'edit_alt' => 'Alt-tekst bewerken',
        'alt_required' => 'Alt-tekst is verplicht bij elke afbeelding.',
        'alt_save_failed' => 'De alt-tekst kon niet worden opgeslagen.',
        'no_images' => 'Er zijn nog geen afbeeldingen geüpload.',
        'alt_dialog_description' => 'Beschrijf kort wat er op de afbeelding te zien is. Deze tekst wordt voorgelezen door schermlezers en getoond als de afbeelding niet laadt.',
        'alt_label' => 'Alt-tekst',
        'kind_document' => 'Document',
        'kind_video' => 'Video',
        // Shared by both file pickers, which show the same list of documents
        // and videos and differ only in what picking one does.
        'search' => 'Zoeken op naam',
        'search_placeholder' => 'Bijvoorbeeld: werkblad',
        'no_results' => 'Geen bestanden gevonden.',
        'preview' => 'Voorbeeld',
        'open' => 'Openen',
        'no_files' => 'Er zijn nog geen documenten of video’s geüpload.',
        'video_preview_description' => 'De video wordt gestreamd met ondersteuning voor doorspoelen.',
        // Shown by the picker dialogs when a search returns more matches than
        // fit in one page of results — same idea as icons.capped below.
        'capped' => ':count bestanden, verfijn je zoekopdracht',
    ],

    'icons' => [
        'none_chosen' => 'Geen icoon gekozen',
        'dialog_title' => 'Kies een icoon',
        'search_placeholder' => 'Zoek een icoon…',
        'filter_label' => 'Filter op verzameling',
        'all' => 'Alles',
        'none' => 'Geen icoon',
        'no_results' => 'Geen iconen gevonden.',
        'capped' => ':count iconen, verfijn je zoekopdracht',
    ],

    'editor' => [
        'aria_label' => 'Pagina-inhoud',
        'placeholder' => 'Schrijf hier de inhoud van deze pagina…',
        'unsaved' => 'Er zijn niet-opgeslagen wijzigingen.',
        'saved' => 'Alle wijzigingen zijn opgeslagen.',
        'save' => 'Inhoud opslaan',
        'bold' => 'Vet',
        'italic' => 'Cursief',
        // H₂O and m/s² are unwritable without these, so the examples stay in
        // the label rather than in a tooltip nobody opens.
        'subscript' => 'Subscript (H₂O)',
        'superscript' => 'Superscript (m/s²)',
        'heading' => 'Kop',
        'heading_2' => 'Kop 2',
        'heading_3' => 'Kop 3',
        'align_left' => 'Links uitlijnen',
        'align_center' => 'Centreren',
        'align_right' => 'Rechts uitlijnen',
        'align_justify' => 'Uitvullen',
        'bullet_list' => 'Opsomming',
        'ordered_list' => 'Genummerde lijst',
        'blockquote' => 'Citaat',
        'link' => 'Link',
        'insert_file' => 'Bestand invoegen',
        'insert_images' => 'Afbeeldingen invoegen',
        'insert_image_aside' => 'Afbeelding naast tekst',
        'insert_youtube' => 'YouTube-video invoegen',
        'insert_table' => 'Tabel invoegen',
        'row_above' => 'Rij erboven',
        'row_below' => 'Rij eronder',
        'delete_row' => 'Rij wissen',
        'column_left' => 'Kolom links',
        'column_right' => 'Kolom rechts',
        'delete_column' => 'Kolom wissen',
        'merge_cells' => 'Cellen samenvoegen',
        'delete_table' => 'Tabel wissen',
        'image_not_a_file' => '":name" is een afbeelding. Voeg hem in met de knop "Afbeeldingen invoegen".',

        'link_dialog' => [
            'description' => 'Een adres op deze site begint met een schuine streep, bijvoorbeeld /hoofdstuk-1/les-1.',
            'address' => 'Adres',
            'placeholder' => 'https://voorbeeld.nl',
            'invalid' => 'Gebruik een adres dat begint met http://, https://, mailto: of /.',
            'remove' => 'Link verwijderen',
        ],

        'youtube_dialog' => [
            'description' => 'Plak de link naar de video. Alleen de video-ID wordt opgeslagen en de video wordt zonder tracking-cookies getoond.',
            'label' => 'YouTube-link of video-ID',
            'placeholder' => 'https://www.youtube.com/watch?v=...',
            'invalid' => 'Dit is geen geldige YouTube-link. Plak de volledige link of alleen de video-ID.',
            'insert' => 'Invoegen',
        ],

        'file_dialog' => [
            'description' => 'Kies een document of video uit de mediabibliotheek. Het bestand wordt pas openbaar zodra deze pagina is opgeslagen.',
            'upload_title' => 'Nieuw bestand uploaden',
            'upload_description' => 'Wordt meteen op deze plek in de pagina gezet.',
            'added' => '1 bestand toegevoegd aan de pagina.|:count bestanden toegevoegd aan de pagina.',
            'remember_to_save' => 'Vergeet niet op "Inhoud opslaan" te klikken.',
            'empty' => 'Er zijn nog geen documenten of video’s. Upload er hierboven een, of bij Media.',
        ],

        'image_dialog' => [
            'description' => 'Kies één of meer afbeeldingen. Ze worden als één galerijblok ingevoegd, in de volgorde waarin je ze aanklikt.',
            // Where the phone behaviour is stated. The block is arranged on a
            // wide screen, so otherwise nobody ever sees it stack.
            'description_aside' => 'Kies één afbeelding. De tekst eronder loopt ernaast door. Op een telefoon staat de afbeelding boven de tekst — daar is geen ruimte naast.',
            'upload_title' => 'Nieuwe afbeelding uploaden',
            'upload_description' => 'Komt in de mediabibliotheek en wordt hier meteen aangevinkt.',
            'not_an_image' => '":name" is geen afbeelding. Voeg hem in met de knop "Bestand invoegen".',
            'search' => 'Zoeken',
            'search_placeholder' => 'Bestandsnaam of alt-tekst',
            'empty' => 'Er zijn nog geen afbeeldingen. Upload er hierboven een, of bij Media.',
            'no_results' => 'Geen afbeeldingen gevonden.',
            'insert' => 'Invoegen',
            'insert_count' => ':count invoegen',
        ],

        'blocks' => [
            'file_missing' => 'Dit bestand bestaat niet meer. Verwijder dit blok.',
            'download_block' => 'Downloadblok',
            'images_missing' => 'Deze afbeeldingen bestaan niet meer. Verwijder dit blok.',
            'image_count' => '1 afbeelding|:count afbeeldingen',
            'youtube_invalid' => 'Ongeldige YouTube-video. Verwijder dit blok.',
            'aside_missing' => 'Deze afbeelding bestaat niet meer. Verwijder dit blok.',
            'aside_left' => 'Links van de tekst',
            'aside_right' => 'Rechts van de tekst',
            'aside_small' => 'Klein',
            'aside_medium' => 'Middelgroot',
            'aside_large' => 'Groot',
        ],
    ],

    'password_field' => [
        'label' => 'Wachtwoord',
        'none' => 'Geen wachtwoord',
        'empty' => 'Er zijn nog geen wachtwoorden. Maak er eerst een aan bij Wachtwoorden.',
    ],

    'image_field' => [
        'none' => 'Geen afbeelding',
        'choose' => 'Kiezen',
        'replace' => 'Vervangen',
        // The buttons repeat the field's name because three "Kiezen" buttons
        // on the settings screen are indistinguishable to a screen reader.
        'choose_label' => ':field: kiezen',
        'replace_label' => ':field: vervangen',
        'remove_label' => ':field: verwijderen',
        'dialog_description' => 'Kies een afbeelding uit de mediabibliotheek.',
        'search_placeholder' => 'Zoek op bestandsnaam of alt-tekst',
        'search_label' => 'Zoek een afbeelding',
        'empty' => 'De mediabibliotheek bevat nog geen afbeeldingen.',
        'no_results' => 'Niets gevonden.',
    ],

    'levels' => [
        'title' => 'Niveaus',
        'description' => 'Waarmee je downloads kunt labelen. Leerlingen zien de downloads gegroepeerd per niveau.',
        'name' => 'Naam',
        'new' => 'Nieuw niveau',
        'name_placeholder' => 'Bijvoorbeeld: VMBO-GT',
        'empty' => 'Er zijn nog geen niveaus.',
        // Split so the level's own name can be emphasised in the markup.
        'merge_intro_after' => 'is nog gekoppeld aan :count download(s). Kies naar welk niveau die downloads verhuizen.',
        'merge_into' => 'Samenvoegen met',
        'merge_placeholder' => 'Kies een niveau',
        'merge_confirm' => 'Samenvoegen en verwijderen',
        'confirm_delete' => 'Niveau ":name" verwijderen?',
        'download_count' => ':count download(s)',
        'not_in_use' => 'niet in gebruik',
    ],

    'passwords' => [
        'title' => 'Wachtwoorden',
        'description' => 'Stel een wachtwoord in bij een onderwerp of pagina. Wie het invoert, kan alles openen dat met hetzelfde wachtwoord beveiligd is. De naam is zichtbaar voor leerlingen, zet er dus niets gevoeligs in.',
        'name' => 'Naam',
        'name_placeholder' => 'Bijvoorbeeld: 5 VWO',
        'password' => 'Wachtwoord',
        'new_password' => 'Nieuw wachtwoord',
        'keep_placeholder' => 'Laat leeg om te behouden',
        'change_warning' => 'Als je het wachtwoord wijzigt, moet iedereen die het al had ingevoerd het opnieuw invoeren.',
        'in_use' => 'Haal dit wachtwoord eerst weg bij de onderwerpen en pagina’s die het gebruiken.',
        'empty' => 'Er zijn nog geen wachtwoorden.',
        'confirm_delete' => 'Wachtwoord ":name" verwijderen?',
        'topic_count' => ':count onderwerp(en)',
        'page_count' => ':count pagina(\'s)',
        'not_in_use' => 'niet in gebruik',
    ],

    'backups' => [
        'title' => 'Back-ups',
        'description' => 'Een back-up bevat álles: de teksten, de indeling, de instellingen en elk bestand dat je hebt geüpload. Met één zo\'n bestand zet je de site opnieuw op een andere server.',
        'create' => 'Nu een back-up maken',
        'creating' => 'Bezig… bij veel bestanden duurt dit een paar minuten. Laat dit scherm open staan.',
        'may_take_a_while' => 'Bij veel bestanden duurt dit een paar minuten.',
        'offsite_title' => 'Zet een back-up ergens anders neer.',
        'offsite_body' => 'Zolang het bestand alleen op deze server staat, ben je het samen met de server kwijt. Download hem en bewaar hem op je laptop of een externe schijf.',
        'empty' => 'Er zijn nog geen back-ups gemaakt.',
        'restore_title' => 'Een back-up terugzetten',
        // Split around the two <code> spans, which cannot be inside a string.
        'restore_body_1' => 'Dat gebeurt op de server zelf, niet hier — het wist alles wat er nu staat, en dat is geen knop die per ongeluk ingedrukt moet kunnen worden. De stappen staan in ',
        'restore_body_2' => '. Kort: zet het bestand op de server en voer ',
        'restore_body_3' => ' uit.',
        // The two server guides are one guide in two languages, so the
        // citation follows the interface rather than staying Dutch.
        'restore_doc' => 'docs/onderhoud-en-beveiliging.md',
        'restore_command' => './restore.sh <bestand>',
        'confirm_delete' => 'Back-up van :moment verwijderen? Dit kan niet ongedaan worden gemaakt.',
        'keep' => 'Op deze server worden standaard de :count nieuwste back-ups bewaard als er automatisch wordt opgeruimd.',
    ],

    'media' => [
        'title' => 'Media',
        'description' => 'Afbeeldingen, documenten en video\'s die je in pagina\'s kunt gebruiken.',
        'images' => 'Afbeeldingen',
        'files' => 'Bestanden',
        'empty' => 'Nog niets geüpload.',
        'image_count' => '1 afbeelding.|:count afbeeldingen.',
        'file_count' => '1 bestand (documenten en video\'s).|:count bestanden (documenten en video\'s).',
    ],

    'site' => [
        'title' => 'Instellingen',
        'description' => 'De naam en het logo van de site, en de tekst bovenaan de homepage.',
        'section_site' => 'De site',
        'name' => 'Naam van de site',
        'name_hint' => 'Staat in de titelbalk van de browser en naast het logo.',
        'logo' => 'Logo',
        'logo_hint' => 'Verschijnt linksboven op elke pagina. Laat leeg voor alleen de naam.',
        'favicon' => 'Favicon',
        'favicon_hint' => 'Het kleine icoontje in het tabblad van de browser. Een vierkant PNG van 32 bij 32 pixels werkt het best.',
        'section_home' => 'Homepage',
        'home_hint' => 'Alles hieronder staat bovenaan de homepage. De tegels met hoofdonderwerpen staan er altijd onder en kunnen niet worden weggehaald.',
        'heading' => 'Kop',
        'subheading' => 'Ondertitel',
        'banner' => 'Banner',
        'banner_hint' => 'Brede afbeelding bovenaan de homepage.',
        'text' => 'Tekst',
        'text_hint' => 'Optioneel. Bestanden en video\'s horen op een pagina, niet hier.',

        'section_search' => 'Zoeken',
        'content_language' => 'Taal van je lesmateriaal',
        'content_language_hint' => 'Bepaalt hoe de zoekfunctie woorden herkent, zodat "krachten" ook "kracht" vindt. Dit gaat over de taal waarin jij schrijft, niet over de taal van de knoppen — die kiest elke bezoeker zelf. Als je dit wijzigt, wordt de zoekindex meteen opnieuw opgebouwd.',
        'content_language_dutch' => 'Nederlands',
        'content_language_english' => 'Engels',
    ],

    'settings' => [
        'profile' => [
            'page_title' => 'Profielinstellingen',
            'title' => 'Profiel',
            'description' => 'Pas je naam en e-mailadres aan',
            'saved' => 'Opgeslagen',
        ],

        'appearance' => [
            'title' => 'Weergave',
            'description' => 'Kies of de site licht of donker wordt getoond. Deze keuze geldt alleen op dit apparaat.',
        ],

        'security' => [
            'title' => 'Beveiliging',
            'password_title' => 'Wachtwoord wijzigen',
            'password_description' => 'Gebruik een lang, uniek wachtwoord. Ben je het kwijt, dan is het commando admin:reset-password op de server de enige manier terug.',
            'current_password' => 'Huidig wachtwoord',
            'new_password' => 'Nieuw wachtwoord',
            'repeat_password' => 'Nieuw wachtwoord herhalen',
        ],

        'two_factor' => [
            'title' => 'Tweestapsverificatie',
            'description' => 'Een extra code bij het inloggen, uit een authenticator-app op je telefoon.',
            'enabled_explanation' => 'Bij het inloggen wordt om een code gevraagd die je afleest in de authenticator-app op je telefoon. De code verandert elke dertig seconden.',
            'disabled_explanation' => 'Zet je dit aan, dan vraagt de site bij het inloggen om een code uit een authenticator-app op je telefoon, naast je wachtwoord. Bewaar de herstelcodes die je daarna krijgt op een veilige plek.',
            'disable' => 'Tweestapsverificatie uitzetten',
            'enable' => 'Tweestapsverificatie aanzetten',
            'finish_setup' => 'Instellen afmaken',
            'enabled_heading' => 'Tweestapsverificatie staat aan',
            'scan_or_key' => 'Scan de QR-code met je authenticator-app, of vul de sleutel handmatig in.',
            'scan_or_key_short' => 'Scan de QR-code met je authenticator-app, of vul de sleutel handmatig in',
            'verify_heading' => 'Code controleren',
            'verify_description' => 'Vul de code van zes cijfers uit je authenticator-app in',
            'continue' => 'Doorgaan',
        ],

        'recovery' => [
            'title' => 'Herstelcodes',
            'description' => 'Met een herstelcode kom je binnen als je je telefoon kwijt bent. Bewaar ze in een wachtwoordmanager.',
            'show' => 'Toon herstelcodes',
            'hide' => 'Verberg herstelcodes',
            'loading' => 'Herstelcodes worden geladen',
            'regenerate' => 'Nieuwe codes maken',
            // Split around the button's name so the sentence can put it
            // wherever the language wants it, in bold.
            'single_use_before' => 'Elke herstelcode werkt één keer en vervalt daarna. Heb je er meer nodig, klik dan hierboven op ',
            'single_use_after' => '.',
        ],

        'passkeys' => [
            'title' => 'Passkeys',
            'description' => 'Inloggen zonder wachtwoord, met de vingerafdruk of pincode van je apparaat.',
            'none' => 'Nog geen passkeys',
            'none_hint' => 'Met een passkey log je in zonder wachtwoord',
            'add' => 'Passkey toevoegen',
            'name_placeholder' => 'Bijvoorbeeld: laptop school, iPhone',
            'name_hint' => 'Met een naam herken je later welk apparaat dit is.',
            'save' => 'Passkey opslaan',
            'saving' => 'Bezig…',
            'delete' => 'Passkey verwijderen',
            'delete_sr' => 'Verwijderen',
            'delete_confirm' => 'Weet je zeker dat je de passkey :name wilt verwijderen? Je kunt er daarna niet meer mee inloggen.',
            'name_label' => 'Naam van de passkey',
            'deleting' => 'Bezig…',
            'unsupported' => 'Deze browser ondersteunt geen passkeys.',
            'signing_in' => 'Bezig met inloggen…',
            'sign_in' => 'Inloggen met een passkey',
            'separator' => 'Of ga verder met e-mail',
        ],
    ],

    'public' => [
        'downloads' => [
            'heading' => 'Downloads',
            'my_level' => 'Mijn niveau:',
        ],

        'locked' => [
            'named' => 'Deze pagina is beveiligd. Vul het wachtwoord voor :name in.',
            'unnamed' => 'Deze pagina is beveiligd. Vul het wachtwoord in.',
            'password' => 'Wachtwoord',
            'unlock' => 'Ontgrendelen',
        ],

        'search' => [
            'title' => 'Zoeken',
            'title_for' => 'Zoeken naar :query',
            'field' => 'Zoekterm',
            'placeholder' => 'Bijvoorbeeld: samenvatting',
            'none' => 'Geen resultaten voor “:query”.',
        ],

        'topic' => [
            'empty' => 'Dit onderdeel heeft nog geen inhoud.',
        ],

        'page_empty' => 'Deze pagina heeft nog geen inhoud.',
        'nothing_published' => 'Er is nog geen lesmateriaal gepubliceerd.',
        'video_unsupported' => 'Je browser kan deze video niet afspelen.',
        'youtube_title' => 'YouTube-video',

        'header' => [
            'search' => 'Zoeken',
            'admin' => 'Beheer',
        ],
    ],

];
