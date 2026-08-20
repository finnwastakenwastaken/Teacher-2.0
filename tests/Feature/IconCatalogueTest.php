<?php

namespace Tests\Feature;

use App\Models\Icon;
use App\Models\Topic;
use App\Models\User;
use App\Support\IconCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IconCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function icon(string $key, array $nodes = [['path', ['d' => 'M0 0h24']]]): Icon
    {
        [$library, $name] = explode(':', $key, 2);

        return Icon::query()->create([
            'key' => $key,
            'library' => $library,
            'name' => $name,
            'nodes' => $nodes,
        ]);
    }

    public function test_an_unprefixed_value_is_read_as_lucide()
    {
        // Every icon chosen before the catalogue existed is a bare lucide
        // name. Those rows are never migrated, so this is what keeps them
        // rendering.
        $this->assertSame('lucide:atom', IconCatalogue::normalise('atom'));
        $this->assertSame('tabler:circuit-resistor', IconCatalogue::normalise('tabler:circuit-resistor'));
        $this->assertNull(IconCatalogue::normalise(''));
        $this->assertNull(IconCatalogue::normalise(null));
    }

    public function test_geometry_is_keyed_by_the_value_that_was_asked_for()
    {
        $this->icon('lucide:atom');
        $this->icon('tabler:circuit-resistor');

        $resolved = IconCatalogue::resolve(['atom', 'tabler:circuit-resistor']);

        // The bare name comes back under the bare name, so a caller can look
        // up what it already holds without normalising first.
        $this->assertArrayHasKey('atom', $resolved);
        $this->assertArrayHasKey('tabler:circuit-resistor', $resolved);
        $this->assertSame('lucide', $resolved['atom']['library']);
    }

    public function test_an_unknown_icon_is_absent_rather_than_an_error()
    {
        // A renamed or removed icon must degrade to "no glyph", never to an
        // exception on a public page.
        $this->assertSame([], IconCatalogue::resolve(['lucide:does-not-exist']));
    }

    public function test_the_homepage_sends_geometry_only_for_the_icons_it_draws()
    {
        $this->icon('lucide:atom');
        $this->icon('lucide:flask-conical');

        Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde', 'icon' => 'atom']);
        Topic::query()->create(['title' => 'Verborgen', 'slug' => 'verborgen', 'icon' => 'flask-conical', 'is_hidden' => true]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('icons', 1)
            ->has('icons.atom')
            // The hidden topic is not on the page, so its icon must not be
            // in the payload either.
            ->missing('icons.flask-conical')
            ->etc()
        );
    }

    public function test_search_matches_on_the_name_and_can_filter_by_library()
    {
        $this->icon('lucide:atom');
        $this->icon('tabler:circuit-resistor');
        $this->icon('tabler:circuit-capacitor');

        $all = collect(IconCatalogue::search('circuit', null, 20))->pluck('key');
        $this->assertEqualsCanonicalizing(
            ['tabler:circuit-resistor', 'tabler:circuit-capacitor'],
            $all->all(),
        );

        $lucideOnly = IconCatalogue::search('circuit', 'lucide', 20);
        $this->assertSame([], $lucideOnly);
    }

    public function test_search_treats_wildcards_as_literal_text()
    {
        $this->icon('lucide:atom');

        // An owner typing "%" is searching for a percent sign, not asking for
        // the whole catalogue.
        $this->assertSame([], IconCatalogue::search('%', null, 20));
    }

    public function test_the_icon_endpoint_is_behind_auth()
    {
        $this->get(route('admin.icons.index'))->assertRedirect(route('login'));
    }

    public function test_the_icon_endpoint_returns_geometry_with_each_result()
    {
        $this->icon('tabler:circuit-resistor', [['path', ['d' => 'M2 12h2']]]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.icons.index', ['q' => 'resistor']))
            ->assertOk()
            ->assertJsonPath('icons.0.key', 'tabler:circuit-resistor')
            ->assertJsonPath('icons.0.name', 'circuit-resistor')
            // Geometry travels with the result so the picker can draw the
            // tile without a second request.
            ->assertJsonPath('icons.0.nodes.0.0', 'path')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('capped', false);
    }

    public function test_shorter_names_sort_first()
    {
        $this->icon('lucide:atom');
        $this->icon('tabler:atom-2-filled');

        $keys = collect(IconCatalogue::search('atom', null, 20))->pluck('key')->all();

        $this->assertSame(['lucide:atom', 'tabler:atom-2-filled'], $keys);
    }
}
