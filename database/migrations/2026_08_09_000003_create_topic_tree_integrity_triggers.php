<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enforced by DB trigger, not just app code, because two near-simultaneous
 * requests can each pass an app-level check before either writes its row:
 *
 * 1. A topic's depth is derived from its parent (never client input) and
 *    capped at 2 (three levels total).
 * 2. A slug is unique among siblings — topics and pages under the same
 *    parent share one slug namespace, so a page can't collide with a
 *    sibling topic.
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

            create or replace trigger topics_set_depth_trigger
                before insert or update of parent_id on topics
                for each row execute function topics_set_depth();

            -- When a topic's depth actually changes, nudge every direct
            -- child's parent_id to itself (a no-op value) purely to re-fire
            -- their own "update of parent_id" trigger, cascading the depth
            -- recompute down the tree; a descendant pushed past the depth cap
            -- raises and rolls back the whole move atomically.
            --
            -- Fires on "update of parent_id", not "update of depth": Postgres
            -- decides by the SET list in the triggering UPDATE, not by what a
            -- BEFORE trigger changed — and depth only ever changes as a side
            -- effect of a parent_id update.
            create or replace function topics_cascade_depth() returns trigger as $$
            begin
                if new.depth is distinct from old.depth then
                    update topics set parent_id = parent_id where parent_id = new.id;
                end if;
                return new;
            end;
            $$ language plpgsql;

            create or replace trigger topics_cascade_depth_trigger
                after update of parent_id on topics
                for each row execute function topics_cascade_depth();

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

            create or replace trigger topics_enforce_sibling_slug
                before insert or update of parent_id, slug on topics
                for each row execute function enforce_sibling_slug_uniqueness();

            create or replace trigger pages_enforce_sibling_slug
                before insert or update of topic_id, slug on pages
                for each row execute function enforce_sibling_slug_uniqueness();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop trigger if exists pages_enforce_sibling_slug on pages;
            drop trigger if exists topics_enforce_sibling_slug on topics;
            drop function if exists enforce_sibling_slug_uniqueness();
            drop trigger if exists topics_cascade_depth_trigger on topics;
            drop function if exists topics_cascade_depth();
            drop trigger if exists topics_set_depth_trigger on topics;
            drop function if exists topics_set_depth();
        SQL);
    }
};
