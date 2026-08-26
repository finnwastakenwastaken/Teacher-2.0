<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text search over pages, using the `dutch` configuration for stemming
 * (e.g. "krachten" finds "kracht"). Maintained by a trigger, not the
 * application, so it can't drift from content_text (itself derived — see
 * Page::writeContent). Weighted A/B/C: title, description, body.
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

        // BEFORE avoids a second UPDATE; naming the columns keeps unrelated
        // writes (sort_order, is_hidden) from re-tokenising the body.
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
