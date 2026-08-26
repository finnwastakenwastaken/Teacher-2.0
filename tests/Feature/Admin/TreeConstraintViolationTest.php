<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Topic;
use App\Support\TreeConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seam between a Postgres trigger and a Dutch sentence on a form.
 *
 * Messages used to be matched by searching raw error text for a substring
 * that lives in a migration, so rewording the trigger would silently break
 * matching. The depth branch is also barely reachable over HTTP — the form
 * request rejects an over-deep parent first — so its message would rot
 * unexercised; these tests raise the real database exception directly instead.
 */
class TreeConstraintViolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_depth_cap_is_recognised_by_its_sqlstate()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $middle = Topic::query()->create([
            'title' => 'Sterrenkunde', 'slug' => 'sterrenkunde', 'parent_id' => $root->id,
        ]);
        $deep = Topic::query()->create([
            'title' => 'Zonnestelsel', 'slug' => 'zonnestelsel', 'parent_id' => $middle->id,
        ]);

        try {
            Topic::query()->create(['title' => 'Te diep', 'slug' => 'te-diep', 'parent_id' => $deep->id]);
            $this->fail('The depth trigger did not fire.');
        } catch (QueryException $e) {
            $this->assertSame('TD001', $e->errorInfo[0] ?? null);
            $this->assertSame(
                'Onderwerpen kunnen maximaal 3 niveaus diep zijn.',
                TreeConstraintViolation::message($e)
            );
        }
    }

    public function test_a_sibling_slug_clash_is_recognised_by_its_sqlstate()
    {
        $root = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        Page::query()->create(['title' => 'Planeten', 'slug' => 'planeten', 'topic_id' => $root->id]);

        // A topic and a page under the same parent cannot share a slug — the
        // uniqueness spans both tables, which is why it is a trigger and not
        // a unique index.
        try {
            Topic::query()->create(['title' => 'Planeten', 'slug' => 'planeten', 'parent_id' => $root->id]);
            $this->fail('The sibling-slug trigger did not fire.');
        } catch (QueryException $e) {
            $this->assertSame('TS001', $e->errorInfo[0] ?? null);
            $this->assertNotNull(TreeConstraintViolation::message($e));
        }
    }

    /**
     * The half that matters more: a failure nobody anticipated must not be
     * dressed up as a slug problem. message() returning null is what makes
     * the controller rethrow instead of putting a wrong hint on a field that
     * is perfectly fine.
     */
    public function test_an_unanticipated_database_failure_is_not_claimed_as_a_slug_error()
    {
        try {
            Topic::query()->create(['title' => 'Wees', 'slug' => 'wees', 'parent_id' => 999999]);
            $this->fail('The parent-exists trigger did not fire.');
        } catch (QueryException $e) {
            $this->assertNull(
                TreeConstraintViolation::message($e),
                'A missing parent is not a slug clash and must not be reported as one.'
            );
        }
    }
}
