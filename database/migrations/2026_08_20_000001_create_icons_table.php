<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The icon catalogue: geometry for every icon the owner can choose,
        // generated from the source packages by scripts/build-icon-catalogue.mjs
        // and loaded by `php artisan icons:sync`.
        //
        // This lives in the database rather than in a PHP array or a JavaScript
        // bundle for one reason: it is 4.9 MB across ~15,000 icons, and a page
        // shows a handful. A generated PHP array would have to be materialised
        // in full on every request; shipping it to the browser would put
        // megabytes on the critical path of the student-facing site. A keyed
        // lookup returns only the icons actually on the page.
        //
        // Nothing here is authored — it is derived data, and `icons:sync` is
        // free to replace it wholesale.
        Schema::create('icons', function (Blueprint $table) {
            // "library:name", e.g. lucide:atom or tabler:circuit-resistor.
            // The library prefix is part of the key rather than a separate
            // column-plus-name pair because that is exactly the string stored
            // in topics.icon and pages.icon, and a single key keeps the
            // lookup a plain whereIn.
            $table->string('key')->primary();

            // Denormalised from the key so the picker can offer per-library
            // filtering without a LIKE on the primary key.
            $table->string('library')->index();

            // The searchable part of the key, without the library prefix.
            $table->string('name')->index();

            // The icon's child elements as [tag, attributes] pairs — the same
            // shape lucide uses internally. Structured data, never markup, so
            // the React renderer builds real elements and never has to inject
            // HTML. See the technical reference on why that rule has no exceptions.
            $table->jsonb('nodes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icons');
    }
};
