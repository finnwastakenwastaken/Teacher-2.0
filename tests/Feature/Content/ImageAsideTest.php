<?php

namespace Tests\Feature\Content;

use App\Exceptions\DependentRecordsExistException;
use App\Models\Image;
use App\Models\Page;
use App\Models\Topic;
use App\Support\PageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `imageAside` — one image with the running text flowing beside it.
 *
 * Two things are worth failing the build over. The first is the whitelist
 * itself: a node the editor can produce and App\Support\PageContent does not
 * know about silently stops saving. The second is the reference row, which is
 * the only reason an embedded file is served to anyone who is not logged in —
 * forget it and the picture renders perfectly for the owner and 403s for
 * every student, which is invisible from the admin panel.
 */
class ImageAsideTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_well_formed_aside_survives_whole()
    {
        $ulid = (string) Str::ulid();

        $clean = PageContent::sanitise($this->doc([
            $this->aside($ulid, 'left', 'large'),
        ]));

        $this->assertSame(
            [[
                'type' => 'imageAside',
                'attrs' => ['ulid' => $ulid, 'side' => 'left', 'size' => 'large'],
                'content' => [],
            ]],
            $clean['content']
        );
    }

    public function test_an_aside_without_a_real_ulid_is_dropped()
    {
        $clean = PageContent::sanitise($this->doc([
            $this->aside('../../etc/passwd', 'left', 'small'),
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Blijft staan']]],
        ]));

        $this->assertCount(1, $clean['content']);
        $this->assertSame('paragraph', $clean['content'][0]['type']);
    }

    /**
     * Unlike a ULID, a side and a size are cosmetic. A picture drawn on the
     * wrong side is a shrug; a picture dropped out of a lesson is a hole in
     * it — so these fall back the way colspan does rather than rejecting.
     */
    public function test_a_nonsense_side_or_size_falls_back_and_keeps_the_image()
    {
        $ulid = (string) Str::ulid();

        $clean = PageContent::sanitise($this->doc([
            $this->aside($ulid, 'float:left;content:url(x)', 'w-[999px]'),
        ]));

        $attrs = $clean['content'][0]['attrs'];

        $this->assertSame($ulid, $attrs['ulid']);
        $this->assertSame('right', $attrs['side']);
        $this->assertSame('medium', $attrs['size']);
    }

    /**
     * `side` and `size` are deliberately absent from NULLABLE_ATTRS: both
     * declare a real default in the extension, so TipTap never writes a null
     * for them. If one arrives anyway it must still not take the image with
     * it.
     */
    public function test_a_null_side_or_size_still_keeps_the_image()
    {
        $ulid = (string) Str::ulid();

        $clean = PageContent::sanitise($this->doc([[
            'type' => 'imageAside',
            'attrs' => ['ulid' => $ulid, 'side' => null, 'size' => null],
        ]]));

        $attrs = $clean['content'][0]['attrs'];

        $this->assertSame($ulid, $attrs['ulid']);
        $this->assertSame('right', $attrs['side']);
        $this->assertSame('medium', $attrs['size']);
    }

    public function test_an_invented_attribute_does_not_survive()
    {
        $ulid = (string) Str::ulid();

        $clean = PageContent::sanitise($this->doc([[
            'type' => 'imageAside',
            'attrs' => [
                'ulid' => $ulid,
                'side' => 'left',
                'size' => 'small',
                'style' => 'position:fixed;inset:0',
                'onload' => 'alert(1)',
            ],
        ]]));

        $this->assertSame(
            ['ulid', 'side', 'size'],
            array_keys($clean['content'][0]['attrs'])
        );
    }

    /**
     * A topic introduction and the homepage walk from a file to the *pages*
     * showing it, and neither is a page row — so an aside there would render
     * for the owner and 403 for every student. It is an embed and it is
     * stripped like the rest.
     */
    public function test_an_aside_is_stripped_from_the_without_embeds_whitelist()
    {
        $clean = PageContent::sanitiseWithoutEmbeds($this->doc([
            $this->aside((string) Str::ulid(), 'right', 'medium'),
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Inleiding']]],
        ]));

        $this->assertCount(1, $clean['content']);
        $this->assertSame('paragraph', $clean['content'][0]['type']);
    }

    public function test_an_aside_publishes_its_image_to_anonymous_visitors()
    {
        Storage::fake('local');

        $image = $this->image();
        $page = $this->makePage();

        // In the library but on no page: private.
        $this->get(route('images.show', $image))->assertForbidden();

        $page->writeContent($this->doc([
            $this->aside($image->ulid, 'left', 'small'),
        ]));

        $this->assertSame(1, $page->mediaReferences()->count());
        $this->get(route('images.show', $image))->assertOk();
    }

    public function test_removing_the_aside_makes_the_image_private_again()
    {
        Storage::fake('local');

        $image = $this->image();
        $page = $this->makePage();

        $page->writeContent($this->doc([$this->aside($image->ulid, 'right', 'medium')]));
        $this->get(route('images.show', $image))->assertOk();

        $page->writeContent($this->doc([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zonder plaatje']]],
        ]));

        $this->get(route('images.show', $image))->assertForbidden();
        $this->assertSame(0, $page->mediaReferences()->count());
    }

    /**
     * A reference row is what blocks the delete, so the owner is told which
     * page still shows the picture rather than breaking it silently.
     */
    public function test_an_image_shown_beside_text_cannot_be_deleted()
    {
        Storage::fake('local');

        $image = $this->image();
        $page = $this->makePage();

        $page->writeContent($this->doc([$this->aside($image->ulid, 'left', 'large')]));

        try {
            $image->delete();
            $this->fail('Expected the delete to be blocked.');
        } catch (DependentRecordsExistException $e) {
            // Naming the page is the whole point: "no" with nothing to act on
            // is how an owner ends up deleting the page instead.
            $this->assertStringContainsString('Hefboom', $e->getMessage());
        }

        $this->assertModelExists($image);
    }

    private function doc(array $content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    private function aside(string $ulid, string $side, string $size): array
    {
        return [
            'type' => 'imageAside',
            'attrs' => ['ulid' => $ulid, 'side' => $side, 'size' => $size],
            // TipTap writes an empty content array for an atom node.
            'content' => [],
        ];
    }

    private function makePage(): Page
    {
        $topic = Topic::query()->create(['title' => 'Krachten', 'slug' => 'krachten']);

        return Page::query()->create([
            'title' => 'Hefboom',
            'slug' => 'hefboom',
            'topic_id' => $topic->id,
        ]);
    }

    private function image(): Image
    {
        $path = 'images/2026/08/'.Str::ulid().'.webp';
        Storage::disk('local')->put($path, 'bytes');

        return Image::query()->create([
            'path' => $path,
            'alt_text' => 'Een hefboom met het draaipunt in het midden',
            'size_bytes' => 5,
            'mime' => 'image/webp',
            'original_filename' => 'hefboom.webp',
        ]);
    }
}
