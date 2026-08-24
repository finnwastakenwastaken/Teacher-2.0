<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let the owner say which language they write in, instead of assuming Dutch.
 *
 * Offering an English interface implies a teacher who may write in English,
 * whose pages would then be stemmed by Dutch rules — "forces" would not find
 * "force", and the stop-word list would be wrong throughout.
 *
 * This is additive by construction. The trigger function stops *naming* a
 * configuration and starts *asking* for one, and the thing it asks falls back
 * to `dutch`; a database that never gets a `content_language` row behaves
 * exactly as it does today.
 *
 * The lookup goes through `pg_ts_config` rather than casting the stored value
 * to `regconfig` directly. A cast turns one wrong settings row into an
 * exception on every page save — and the value arrives from a form, through
 * jsonb, from a table anyone with database access can edit. Falling back is
 * the only acceptable failure here.
 *
 * STABLE, not VOLATILE: it reads a table but nothing changes underneath a
 * single statement, so the planner may call it once per statement instead of
 * once per row. It is one extra SELECT on a write performed by one person a
 * few times a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function content_search_config() returns regconfig as $$
                select coalesce(
                    (select cfgname::regconfig
                       from pg_ts_config
                      where cfgname = (select value #>> '{}'
                                         from settings
                                        where key = 'content_language')),
                    'dutch'::regconfig);
            $$ language sql stable;
        SQL);

        DB::statement(<<<'SQL'
            create or replace function pages_search_vector_update() returns trigger as $$
            declare
                config regconfig := content_search_config();
            begin
                new.search_vector :=
                    setweight(to_tsvector(config, coalesce(new.title, '')), 'A') ||
                    setweight(to_tsvector(config, coalesce(new.description, '')), 'B') ||
                    setweight(to_tsvector(config, coalesce(new.content_text, '')), 'C');
                return new;
            end
            $$ language plpgsql;
        SQL);
    }

    public function down(): void
    {
        // Back to the hard-coded configuration, then drop the function —
        // in that order, or the trigger is left calling something gone.
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

        DB::statement('drop function if exists content_search_config()');
    }
};
