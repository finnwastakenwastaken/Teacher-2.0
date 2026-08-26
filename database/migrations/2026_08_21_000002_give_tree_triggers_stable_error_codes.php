<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives PHP a stable error code instead of matching Dutch error prose (the
 * controller was substring-matching 'niveaus diep', which silently breaks the
 * moment the message is reworded). SQLSTATE codes TD001-3/TS001 use the TD/TS
 * classes, unused by PostgreSQL itself, so they can't collide with a
 * built-in condition. Messages are unchanged; function bodies are otherwise
 * reproduced verbatim from 2026_08_09_000003 via `create or replace` rather
 * than editing that already-run migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function topics_set_depth() returns trigger as $$
            declare
                parent_depth smallint;
            begin
                if new.parent_id is null then
                    new.depth := 0;
                else
                    if new.id is not null and new.parent_id = new.id then
                        raise exception 'A topic cannot be its own parent'
                            using errcode = 'TD002';
                    end if;

                    select depth into parent_depth from topics where id = new.parent_id;
                    if parent_depth is null then
                        raise exception 'Parent topic % does not exist', new.parent_id
                            using errcode = 'TD003';
                    end if;

                    new.depth := parent_depth + 1;
                end if;

                if new.depth > 2 then
                    raise exception 'Onderwerpen kunnen maximaal 3 niveaus diep zijn.'
                        using errcode = 'TD001';
                end if;

                return new;
            end;
            $$ language plpgsql;

            create or replace function enforce_sibling_slug_uniqueness() returns trigger as $$
            declare
                scope_id bigint;
                conflict_exists boolean;
            begin
                if tg_table_name = 'topics' then
                    scope_id := new.parent_id;
                else
                    scope_id := new.topic_id;
                end if;

                select exists(
                    select 1 from topics
                    where parent_id is not distinct from scope_id
                      and slug = new.slug
                      and not (tg_table_name = 'topics' and id = new.id)
                    union all
                    select 1 from pages
                    where topic_id = scope_id
                      and slug = new.slug
                      and not (tg_table_name = 'pages' and id = new.id)
                ) into conflict_exists;

                if conflict_exists then
                    raise exception 'De slug "%" is al in gebruik binnen dit onderdeel.', new.slug
                        using errcode = 'TS001';
                end if;

                return new;
            end;
            $$ language plpgsql;
        SQL);
    }

    public function down(): void
    {
        // Back to raising without a code. The messages never changed, so the
        // substring match this replaced still works against these.
        DB::unprepared(<<<'SQL'
            create or replace function topics_set_depth() returns trigger as $$
            declare
                parent_depth smallint;
            begin
                if new.parent_id is null then
                    new.depth := 0;
                else
                    if new.id is not null and new.parent_id = new.id then
                        raise exception 'A topic cannot be its own parent';
                    end if;

                    select depth into parent_depth from topics where id = new.parent_id;
                    if parent_depth is null then
                        raise exception 'Parent topic % does not exist', new.parent_id;
                    end if;

                    new.depth := parent_depth + 1;
                end if;

                if new.depth > 2 then
                    raise exception 'Onderwerpen kunnen maximaal 3 niveaus diep zijn.';
                end if;

                return new;
            end;
            $$ language plpgsql;

            create or replace function enforce_sibling_slug_uniqueness() returns trigger as $$
            declare
                scope_id bigint;
                conflict_exists boolean;
            begin
                if tg_table_name = 'topics' then
                    scope_id := new.parent_id;
                else
                    scope_id := new.topic_id;
                end if;

                select exists(
                    select 1 from topics
                    where parent_id is not distinct from scope_id
                      and slug = new.slug
                      and not (tg_table_name = 'topics' and id = new.id)
                    union all
                    select 1 from pages
                    where topic_id = scope_id
                      and slug = new.slug
                      and not (tg_table_name = 'pages' and id = new.id)
                ) into conflict_exists;

                if conflict_exists then
                    raise exception 'De slug "%" is al in gebruik binnen dit onderdeel.', new.slug;
                end if;

                return new;
            end;
            $$ language plpgsql;
        SQL);
    }
};
