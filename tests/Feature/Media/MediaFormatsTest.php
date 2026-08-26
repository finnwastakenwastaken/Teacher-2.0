<?php

namespace Tests\Feature\Media;

use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use App\Support\MediaFormats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * What the uploader is allowed to claim it accepts.
 *
 * The bug this answers is a usability one with an expensive tail: a teacher
 * found out which video formats work by uploading one and waiting, and behind
 * a Cloudflare tunnel that wait is measured in gigabytes. Stating the formats
 * is only worth anything if the statement is true, so these tests are about
 * the list coming from config/media.php rather than from a sentence somebody
 * typed — a second list is the only way this feature can rot.
 */
class MediaFormatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_accepted_type_is_listed_under_its_own_kind()
    {
        $listed = MediaFormats::byKind();

        foreach (config('media.types') as $mime => $type) {
            $this->assertContains(
                $type['extension'],
                $listed[$type['kind']],
                "config/media.php accepts {$mime} and the uploader never says so."
            );
        }
    }

    /**
     * KINDS is the complete set, not just a display order.
     *
     * A kind added to config without a label in the uploader would be
     * accepted by the server and never mentioned on screen — which is exactly
     * the failure App\Support\MediaFormats exists to prevent, so it has to
     * fail the build rather than pass quietly.
     */
    public function test_config_names_no_kind_the_uploader_cannot_label()
    {
        $this->assertSame(
            MediaFormats::KINDS,
            array_keys(MediaFormats::byKind()),
            'config/media.php names a kind MediaFormats::KINDS does not, so the '
                .'uploader would accept it silently. Add a label to '
                .'FORMAT_KIND_KEYS in media-uploader.tsx and to lang/*/ui.php.'
        );
    }

    /** Video leads: it is the group where a wrong guess costs the upload. */
    public function test_video_is_listed_first_and_names_the_formats_the_server_takes()
    {
        $listed = MediaFormats::byKind();

        $this->assertSame('video', array_key_first($listed));
        $this->assertSame(['mp4', 'webm', 'mov'], $listed['video']);
    }

    /**
     * `.jpeg` is accepted and `image/jpeg` writes `.jpg`, so reading the type
     * table alone would under-state what a teacher may drop in. The aliases
     * live in config beside that table so both readers see the same set —
     * App\Services\MediaLibrary when sniffing is ambiguous, and this when the
     * screen has to say so.
     */
    public function test_an_extension_that_is_not_the_one_written_to_disk_is_still_listed()
    {
        $images = MediaFormats::byKind()['image'];

        $this->assertContains('jpg', $images);
        $this->assertContains('jpeg', $images);

        foreach (config('media.extension_aliases') as $extension => $mime) {
            $kind = config('media.types')[$mime]['kind'];

            $this->assertContains((string) $extension, MediaFormats::byKind()[$kind]);
        }
    }

    /**
     * MEDIA_ALLOW_SVG refuses the upload while the row stays in the table, so
     * the row alone cannot decide. Telling a teacher an SVG is welcome and
     * then refusing it is the same bug as never telling them at all.
     */
    public function test_svg_disappears_from_the_list_when_it_is_refused()
    {
        $this->assertContains('svg', MediaFormats::byKind()['image']);

        config(['media.allow_svg' => false]);

        $this->assertNotContains('svg', MediaFormats::byKind()['image']);
        // Only that one goes; the rest of the group is untouched.
        $this->assertContains('png', MediaFormats::byKind()['image']);
    }

    public function test_nothing_is_listed_twice()
    {
        foreach (MediaFormats::byKind() as $kind => $extensions) {
            $this->assertSame(
                array_values(array_unique($extensions)),
                $extensions,
                "The {$kind} list repeats an extension."
            );
        }
    }

    // ------------------------------------------------------------------
    // The screens that have to be handed the list.
    // ------------------------------------------------------------------

    public function test_the_media_screen_is_sent_the_accepted_formats()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.media.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('acceptedFormats', MediaFormats::byKind())
                ->etc());
    }

    /**
     * The editor uploads too, into the body and into the downloads section,
     * and a drop zone that cannot say what it takes is the same gap wherever
     * it is drawn.
     */
    public function test_the_page_editor_is_sent_the_accepted_formats()
    {
        $topic = Topic::query()->create([
            'title' => 'Hoofdstuk 1',
            'slug' => 'hoofdstuk-1',
        ]);

        $page = Page::query()->create([
            'title' => 'Les 1',
            'slug' => 'les-1',
            'topic_id' => $topic->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('acceptedFormats', MediaFormats::byKind())
                ->etc());
    }
}
