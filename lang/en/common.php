<?php

/*
 * Strings shared by more than one screen, on both sides of the site.
 *
 * The dotted key is the identity, never the English text: keying on the
 * source string means editing a line silently breaks the other locale's
 * lookup, and nothing would report it. LocalisationTest holds the two locales
 * to the same key set.
 */

return [

    'locale' => [
        'label' => 'Language',
        'nl' => 'Dutch',
        'en' => 'English',
        // Said out loud to anyone using a screen reader, because the control
        // itself is two short words that do not explain what changes.
        'description' => 'Changes the language of the site itself only. Course material stays in the language it was written in.',
    ],

];
