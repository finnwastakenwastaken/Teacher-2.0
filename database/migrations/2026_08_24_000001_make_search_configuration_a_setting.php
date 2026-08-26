<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets the owner declare which language they write content in, instead of
 * assuming Dutch — an English-interface teacher may still write English
 * pages, which Dutch stemming would search badly.
 *
 * Additive: the trigger now asks `content_search_config()` for a
 * configuration instead of naming one, falling back to `dutch` when unset —
 * an un-migrated database behaves exactly as before.
 *
 * Looked up via `pg_ts_config` rather than a `regconfig` cast, so a bad
 * settings value falls back instead of throwing on every page save.
 *
 * STABLE, not VOLATILE: safe since nothing changes mid-statement, and lets
 * the planner call it once per statement rather than per row.
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
