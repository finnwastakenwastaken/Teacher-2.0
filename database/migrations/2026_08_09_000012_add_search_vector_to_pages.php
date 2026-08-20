<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text search over pages, in PostgreSQL, with the `dutch` configuration.
 *
 * The `dutch` config gives stemming and stop words, so "krachten" finds
 * "kracht" — which an ILIKE scan would not, and which matters more in Dutch
 * than in English because of compounding.
 *
 * Maintained by a trigger rather than by the application: `content_text` is
 * itself derived (see Page::writeContent), and a second derivation done in
 * PHP would be one more thing that can silently fall out of step with the
 * row it describes. The database is the only writer here.
 *
 * Weighted, so a match in a title outranks a passing mention in a body:
 *   A title · B description · C body text
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // No Blueprint type for tsvector.
            $table->addColumn('tsvector', 'search_vector')->nullable();
        });

        DB::statement(<<<'SQL'
            create or replace function pages_search_vector_update() returns trigger as $$
            begin
                new.search_vector :=
                    setweight(to_tsvector('dutch', coalesce(new.title, '')), 'A') ||
                    setweight(to_tsvector('dutch', coalesce(new.description, '')), 'B') ||
                    setweight(to_tsvector('dutch', coalesce(new.content_text, '')), 'C');
                return new;
            end
            $$ language plpgsql;
        SQL);

        // BEFORE, so the computed value is part of the row being written
        // rather than a second UPDATE. Listing the columns keeps unrelated
        // writes (sort_order, is_hidden) from re-tokenising the whole body —
        // note that Postgres decides this from the statement's SET list, not
        // from which values actually changed.
        DB::statement(<<<'SQL'
            create or replace trigger pages_search_vector_trigger
                before insert or update of title, description, content_text on pages
                for each row execute function pages_search_vector_update();
        SQL);

        DB::statement('create index pages_search_vector_index on pages using gin (search_vector)');

        // Backfill anything that already exists.
        DB::statement('update pages set title = title');
    }

    public function down(): void
    {
        DB::statement('drop index if exists pages_search_vector_index');
        DB::statement('drop trigger if exists pages_search_vector_trigger on pages');
        DB::statement('drop function if exists pages_search_vector_update()');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });
    }
};
