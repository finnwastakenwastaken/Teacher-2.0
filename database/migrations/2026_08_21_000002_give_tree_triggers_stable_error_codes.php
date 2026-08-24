<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let PHP recognise these two refusals by a code rather than by their prose.
 *
 * Both triggers raise a message written in Dutch, and the controller decided
 * which friendly error to show the owner by searching the raw Postgres error
 * text for the substring 'niveaus diep'. That works until someone rewords the
 * message — at which point the branch stops matching, forever, with no test
 * failing and the owner getting "this change could not be saved" for a depth
 * violation that has a perfectly good explanation.
 *
 * Branching on the text of an error message across a language boundary is the
 * actual defect; the wording is not an API. plpgsql lets `raise` carry an
 * SQLSTATE, so each one gets a code that PHP can switch on and nobody will
 * ever be tempted to translate.
 *
 * TD001 / TS001: classes TD and TS are not used by PostgreSQL itself (it
 * reserves 00–42, 44, 53–58, 72, F0, HV, P0 and XX), so they cannot collide
 * with a built-in condition.
 *
 * The messages are unchanged. Only the codes are new, and the function bodies
 * are otherwise reproduced verbatim from
 * 2026_08_09_000003_create_topic_tree_integrity_triggers — `create or replace`
 * rather than editing that migration, which has already run everywhere.
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
