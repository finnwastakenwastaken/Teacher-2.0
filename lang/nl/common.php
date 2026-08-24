<?php

/*
 * Strings shared by more than one screen, on both sides of the site.
 *
 * The dotted key is the identity, never the Dutch text: keying on the source
 * string means editing a Dutch line silently breaks the English lookup, and
 * nothing would report it. LocalisationTest holds the two locales to the same
 * key set.
 */

return [

    'locale' => [
        'label' => 'Taal',
        'nl' => 'Nederlands',
        'en' => 'Engels',
        // Said out loud to anyone using a screen reader, because the control
        // itself is two short words that do not explain what changes.
        'description' => 'Verandert alleen de taal van de website zelf. Lesmateriaal blijft in de taal waarin het geschreven is.',
    ],

];
