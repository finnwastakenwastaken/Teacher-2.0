<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two invariants from the technical reference's content model that must hold even under
 * concurrent writes, so they are enforced here rather than only in the
 * application layer — the same reasoning as the admin-claim advisory lock:
 * two near-simultaneous requests can each pass an app-level check before
 * either has written its row.
 *
 * 1. A topic's depth is always derived from its parent, never trusted from
 *    client input, and can never exceed 2 (three levels total).
 * 2. A slug is unique among siblings, where "siblings" means every topic and
 *    every page attached directly under the same parent topic (or, for root
 *    topics, every other root topic) — pages and topics share one slug
 *    namespace per parent so a page can never collide with a child topic.
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

            -- A topic's depth is recomputed by the trigger above whenever its
            -- own parent_id changes. When that recompute actually changes the
            -- depth value, this trigger nudges every direct child's parent_id
            -- (to itself — a no-op value) purely to make Postgres re-fire
            -- their own "update of parent_id" trigger, which recomputes
            -- THEIR depth in turn, cascading down the tree. A move that would
            -- push any descendant past the depth cap raises inside that
            -- chain and rolls back the entire move atomically.
            --
            -- This fires on "update of parent_id", not "update of depth":
            -- Postgres decides whether an "UPDATE OF <column>" trigger fires
            -- by checking whether that column is named in the SQL UPDATE's
            -- SET list, not by checking which columns a BEFORE trigger went
            -- on to change. The depth recompute above only ever happens as a
            -- side effect of a parent_id update, so that is the column this
            -- trigger has to watch.
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
