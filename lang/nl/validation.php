<?php

/*
 * Dutch validation messages.
 *
 * This file did not exist until now, and its absence was a visible bug rather
 * than a missing nicety: APP_LOCALE and APP_FALLBACK_LOCALE are both `nl`, so
 * every message fell through to the framework's own English file. A Dutch
 * screen returned "The password field must be at least 12 characters."
 *
 * Keep the key set identical to lang/en/validation.php. LocalisationTest
 * compares the two recursively, because a key present in one locale and not
 * the other surfaces as a raw key path on screen — "validation.required" —
 * which looks like a crash rather than a translation gap.
 */

return [

    'accepted' => ':attribute moet geaccepteerd worden.',
    'accepted_if' => ':attribute moet geaccepteerd worden als :other :value is.',
    'active_url' => ':attribute is geen geldige URL.',
    'after' => ':attribute moet een datum na :date zijn.',
    'after_or_equal' => ':attribute moet een datum na of gelijk aan :date zijn.',
    'alpha' => ':attribute mag alleen letters bevatten.',
    'alpha_dash' => ':attribute mag alleen letters, cijfers, liggende streepjes en onderstrepingstekens bevatten.',
    'alpha_num' => ':attribute mag alleen letters en cijfers bevatten.',
    'any_of' => ':attribute is ongeldig.',
    'array' => ':attribute moet een lijst zijn.',
    'array_keys' => ':attribute mag alleen de volgende sleutels bevatten: :values.',
    'ascii' => ':attribute mag alleen letters, cijfers en leestekens zonder accenten bevatten.',
    'base64' => ':attribute moet een geldige Base64-tekst zijn.',
    'before' => ':attribute moet een datum voor :date zijn.',
    'before_or_equal' => ':attribute moet een datum voor of gelijk aan :date zijn.',
    'between' => [
        'array' => ':attribute moet tussen de :min en :max items bevatten.',
        'file' => ':attribute moet tussen de :min en :max kilobytes zijn.',
        'numeric' => ':attribute moet tussen :min en :max liggen.',
        'string' => ':attribute moet tussen de :min en :max tekens lang zijn.',
    ],
    'boolean' => ':attribute moet ja of nee zijn.',
    'can' => ':attribute bevat een waarde die niet is toegestaan.',
    // The `attributes` map below is capitalised, because nearly every message
    // here opens with :attribute. The few that would otherwise carry it
    // mid-sentence are worded to open with it too — "De bevestiging van Het
    // wachtwoord" is the sort of thing that reads as machine-generated.
    'confirmed' => ':attribute komt niet overeen met de bevestiging.',
    'contains' => ':attribute mist een verplichte waarde.',
    'current_password' => 'Het wachtwoord is onjuist.',
    'date' => ':attribute is geen geldige datum.',
    'date_equals' => ':attribute moet gelijk zijn aan :date.',
    'date_format' => ':attribute komt niet overeen met het formaat :format.',
    'decimal' => ':attribute moet :decimal cijfers achter de komma hebben.',
    'declined' => ':attribute moet afgewezen worden.',
    'declined_if' => ':attribute moet afgewezen worden als :other :value is.',
    'different' => ':attribute en :other mogen niet hetzelfde zijn.',
    'digits' => ':attribute moet uit :digits cijfers bestaan.',
    'digits_between' => ':attribute moet tussen de :min en :max cijfers lang zijn.',
    'dimensions' => ':attribute heeft ongeldige afmetingen.',
    'distinct' => ':attribute bevat een dubbele waarde.',
    'doesnt_contain' => ':attribute mag geen van de volgende bevatten: :values.',
    'doesnt_end_with' => ':attribute mag niet eindigen op een van de volgende: :values.',
    'doesnt_start_with' => ':attribute mag niet beginnen met een van de volgende: :values.',
    'email' => ':attribute is geen geldig e-mailadres.',
    'encoding' => ':attribute moet gecodeerd zijn in :encoding.',
    'ends_with' => ':attribute moet eindigen op een van de volgende: :values.',
    'enum' => ':attribute is ongeldig.',
    'exists' => ':attribute bestaat niet.',
    'extensions' => ':attribute moet een van de volgende bestandstypen hebben: :values.',
    'file' => ':attribute moet een bestand zijn.',
    'filled' => ':attribute mag niet leeg zijn.',
    'gt' => [
        'array' => ':attribute moet meer dan :value items bevatten.',
        'file' => ':attribute moet groter zijn dan :value kilobytes.',
        'numeric' => ':attribute moet groter zijn dan :value.',
        'string' => ':attribute moet langer zijn dan :value tekens.',
    ],
    'gte' => [
        'array' => ':attribute moet :value items of meer bevatten.',
        'file' => ':attribute moet groter of gelijk zijn aan :value kilobytes.',
        'numeric' => ':attribute moet groter of gelijk zijn aan :value.',
        'string' => ':attribute moet minstens :value tekens lang zijn.',
    ],
    'hex_color' => ':attribute is geen geldige hexadecimale kleurcode.',
    'image' => ':attribute moet een afbeelding zijn.',
    'in' => ':attribute is ongeldig.',
    'in_array' => ':attribute komt niet voor in :other.',
    'in_array_keys' => ':attribute moet minstens een van de volgende sleutels bevatten: :values.',
    'integer' => ':attribute moet een heel getal zijn.',
    'ip' => ':attribute is geen geldig IP-adres.',
    'ipv4' => ':attribute is geen geldig IPv4-adres.',
    'ipv6' => ':attribute is geen geldig IPv6-adres.',
    'json' => ':attribute is geen geldige JSON-tekst.',
    'list' => ':attribute moet een lijst zijn.',
    'lowercase' => ':attribute mag alleen kleine letters bevatten.',
    'lt' => [
        'array' => ':attribute moet minder dan :value items bevatten.',
        'file' => ':attribute moet kleiner zijn dan :value kilobytes.',
        'numeric' => ':attribute moet kleiner zijn dan :value.',
        'string' => ':attribute moet korter zijn dan :value tekens.',
    ],
    'lte' => [
        'array' => ':attribute mag niet meer dan :value items bevatten.',
        'file' => ':attribute moet kleiner of gelijk zijn aan :value kilobytes.',
        'numeric' => ':attribute moet kleiner of gelijk zijn aan :value.',
        'string' => ':attribute mag niet langer zijn dan :value tekens.',
    ],
    'mac_address' => ':attribute is geen geldig MAC-adres.',
    'max' => [
        'array' => ':attribute mag niet meer dan :max items bevatten.',
        'file' => ':attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':attribute mag niet groter zijn dan :max.',
        'string' => ':attribute mag niet langer zijn dan :max tekens.',
    ],
    'max_digits' => ':attribute mag niet meer dan :max cijfers bevatten.',
    'mimes' => ':attribute moet een bestand zijn van het type: :values.',
    'mimetypes' => ':attribute moet een bestand zijn van het type: :values.',
    'min' => [
        'array' => ':attribute moet minstens :min items bevatten.',
        'file' => ':attribute moet minstens :min kilobytes zijn.',
        'numeric' => ':attribute moet minstens :min zijn.',
        'string' => ':attribute moet minstens :min tekens lang zijn.',
    ],
    'min_digits' => ':attribute moet minstens :min cijfers bevatten.',
    'missing' => ':attribute mag niet aanwezig zijn.',
    'missing_if' => ':attribute mag niet aanwezig zijn als :other :value is.',
    'missing_unless' => ':attribute mag alleen aanwezig zijn als :other :value is.',
    'missing_with' => ':attribute mag niet aanwezig zijn als :values is ingevuld.',
    'missing_with_all' => ':attribute mag niet aanwezig zijn als :values zijn ingevuld.',
    'multiple_of' => ':attribute moet een veelvoud van :value zijn.',
    'not_in' => ':attribute is ongeldig.',
    'not_regex' => ':attribute heeft een ongeldig formaat.',
    'numeric' => ':attribute moet een getal zijn.',
    'password' => [
        'letters' => 'Het wachtwoord moet minstens één letter bevatten.',
        'mixed' => 'Het wachtwoord moet minstens één hoofdletter en één kleine letter bevatten.',
        'numbers' => 'Het wachtwoord moet minstens één cijfer bevatten.',
        'symbols' => 'Het wachtwoord moet minstens één leesteken bevatten, bijvoorbeeld ! ? @ of #.',
        // Deliberately not a literal translation. The English original reads
        // as though this account had been breached; it means the opposite —
        // the password itself is a known one, and the check is done without
        // the password ever leaving this server.
        'uncompromised' => 'Dit wachtwoord komt voor in een bekend datalek en is daarmee makkelijk te raden. Kies een ander wachtwoord.',
    ],
    'present' => ':attribute moet aanwezig zijn.',
    'present_if' => ':attribute moet aanwezig zijn als :other :value is.',
    'present_unless' => ':attribute moet aanwezig zijn tenzij :other :value is.',
    'present_with' => ':attribute moet aanwezig zijn als :values is ingevuld.',
    'present_with_all' => ':attribute moet aanwezig zijn als :values zijn ingevuld.',
    'prohibited' => ':attribute is niet toegestaan.',
    'prohibited_if' => ':attribute is niet toegestaan als :other :value is.',
    'prohibited_if_accepted' => ':attribute is niet toegestaan als :other is geaccepteerd.',
    'prohibited_if_declined' => ':attribute is niet toegestaan als :other is afgewezen.',
    'prohibited_unless' => ':attribute is niet toegestaan tenzij :other voorkomt in :values.',
    'prohibits' => ':attribute zorgt ervoor dat :other niet aanwezig mag zijn.',
    'regex' => ':attribute heeft een ongeldig formaat.',
    'required' => ':attribute is verplicht.',
    'required_array_keys' => ':attribute moet waarden bevatten voor: :values.',
    'required_if' => ':attribute is verplicht als :other :value is.',
    'required_if_accepted' => ':attribute is verplicht als :other is geaccepteerd.',
    'required_if_declined' => ':attribute is verplicht als :other is afgewezen.',
    'required_unless' => ':attribute is verplicht tenzij :other voorkomt in :values.',
    'required_with' => ':attribute is verplicht als :values is ingevuld.',
    'required_with_all' => ':attribute is verplicht als :values zijn ingevuld.',
    'required_without' => ':attribute is verplicht als :values niet is ingevuld.',
    'required_without_all' => ':attribute is verplicht als geen van :values is ingevuld.',
    'same' => ':attribute moet overeenkomen met :other.',
    'size' => [
        'array' => ':attribute moet :size items bevatten.',
        'file' => ':attribute moet :size kilobytes zijn.',
        'numeric' => ':attribute moet :size zijn.',
        'string' => ':attribute moet :size tekens lang zijn.',
    ],
    'starts_with' => ':attribute moet beginnen met een van de volgende: :values.',
    'string' => ':attribute moet tekst zijn.',
    'timezone' => ':attribute is geen geldige tijdzone.',
    'unique' => ':attribute is al in gebruik.',
    'uploaded' => ':attribute kon niet worden geüpload.',
    'uppercase' => ':attribute mag alleen hoofdletters bevatten.',
    'url' => ':attribute is geen geldige URL.',
    'ulid' => ':attribute is geen geldige ULID.',
    'uuid' => ':attribute is geen geldige UUID.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
     * Without these the messages read ":attribute is verplicht" with the raw
     * column name substituted in — "password is verplicht". Only the fields
     * this application actually validates are listed; anything unlisted falls
     * back to the humanised column name, which is the right default.
     */
    'attributes' => [
        'alt_text' => 'De alternatieve tekst',
        'content' => 'De inhoud',
        'current_password' => 'Het huidige wachtwoord',
        'description' => 'De omschrijving',
        'education_levels' => 'De niveaus',
        'email' => 'Het e-mailadres',
        'file' => 'Het bestand',
        'home_heading' => 'De titel op de homepagina',
        'home_subheading' => 'De ondertitel op de homepagina',
        'icon' => 'Het pictogram',
        'label' => 'Het label',
        'locale' => 'De taal',
        'name' => 'De naam',
        'parent_id' => 'Het bovenliggende onderwerp',
        'password' => 'Het wachtwoord',
        'password_confirmation' => 'De wachtwoordbevestiging',
        'setup_token' => 'De installatiecode',
        'site_title' => 'De naam van de site',
        'slug' => 'De URL-naam',
        'title' => 'De titel',
        'topic_id' => 'Het onderwerp',
    ],

];
