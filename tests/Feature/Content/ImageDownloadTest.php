<?php

namespace Tests\Feature\Content;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A poster, a scanned worksheet or a diagram can be handed out.
 *
 * The library a file lands in is decided by sniffing its bytes, so anything
 * raster is an `images` row — and a download attachment used to be able to
 * name only `media_files`. It names either now, which makes this a *second*
 * way an image becomes public, and the access consequences are the point of
 * most of what follows.
 *
 * One image record, two ways to reach it: there is deliberately no second
 * copy in `media_files`, so the same picture embedded in a lesson and offered
 * as a printable handout is one row used twice.
 */
class ImageDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makePage(string $slug = 'de-planeten', ?Topic $topic = null): Page
    {
        $topic ??= Topic::query()->firstOrCreate(
            ['parent_id' => null, 'slug' => 'natuurkunde'],
            ['title' => 'Natuurkunde']
        );

        return Page::query()->create([
            'title' => 'De Planeten',
            'slug' => $slug,
            'topic_id' => $topic->id,
        ]);
    }

    private function makeImage(string $name = 'poster.webp', string $mime = 'image/webp'): Image
    {
        $path = 'images/2026/08/'.Str::ulid().'.webp';
        Storage::disk('local')->put($path, 'bytes');

        return Image::query()->create([
            'path' => $path,
            'alt_text' => 'Een poster over de planeten',
            'width' => 1200,
            'height' => 800,
            'size_bytes' => 5,
            'mime' => $mime,
            'original_filename' => $name,
        ]);
    }

    private function makeFile(string $name = 'werkblad.pdf'): MediaFile
    {
        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');

        return MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => $name,
        ]);
    }

    private function unlockCookie(AccessPassword $password): array
    {
        return ['unlock_'.$password->id => substr(hash('sha256', $password->password_hash), 0, 32)];
    }

    // -----------------------------------------------------------------
    // The schema. Each of these is enforced in the database rather than
    // only in the Form Request, because a second writer — a console
    // command, a future action — would otherwise be able to create rows
    // no screen can represent.
    // -----------------------------------------------------------------

    public function test_an_attachment_naming_neither_library_is_refused_by_the_database()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $this->expectException(QueryException::class);

        DB::table('page_downloads')->insert([
            'ulid' => (string) Str::ulid(),
            'page_id' => $page->id,
            'media_file_id' => null,
            'image_id' => null,
            'sort_order' => 0,
            'downloads_count' => 0,
        ]);
    }

    public function test_an_attachment_naming_both_libraries_is_refused_by_the_database()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $this->expectException(QueryException::class);

        DB::table('page_downloads')->insert([
            'ulid' => (string) Str::ulid(),
            'page_id' => $page->id,
            'media_file_id' => $this->makeFile()->id,
            'image_id' => $this->makeImage()->id,
            'sort_order' => 0,
            'downloads_count' => 0,
        ]);
    }

    /**
     * PostgreSQL treats NULLs as distinct in a unique index by default, so a
     * single composite unique across both columns would have let the same
     * image be attached to the same page any number of times — every row
     * carrying a null media_file_id being unique from every other one. Two
     * partial unique indexes are what actually says "a page offers this once".
     */
    public function test_a_page_cannot_offer_the_same_image_twice()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();

        $page->downloads()->create(['image_id' => $image->id]);

        $this->expectException(QueryException::class);

        $page->downloads()->create(['image_id' => $image->id]);
    }

    public function test_two_pages_may_each_offer_the_same_image_with_their_own_levels()
    {
        Storage::fake('local');
        $image = $this->makeImage();
        $havo = EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 0]);
        $vwo = EducationLevel::query()->create(['name' => 'VWO', 'slug' => 'vwo', 'sort_order' => 1]);

        $first = $this->makePage('de-planeten');
        $second = $this->makePage('oefenen');

        $a = $first->downloads()->create(['image_id' => $image->id]);
        $a->educationLevels()->sync([$havo->id]);

        $b = $second->downloads()->create(['image_id' => $image->id]);
        $b->educationLevels()->sync([$vwo->id]);

        // Level tags hang off the attachment, not the file — the same rule
        // that already holds for documents, and it must go through the same
        // pivot rather than growing a second one.
        $this->assertSame([$havo->id], $a->educationLevels->pluck('id')->all());
        $this->assertSame([$vwo->id], $b->fresh()->educationLevels->pluck('id')->all());
    }

    // -----------------------------------------------------------------
    // Access. An image offered as a download is published by that
    // attachment, and inherits the page's password like everything else
    // on it — through App\Support\MediaAccess::pagesShowing(), not
    // through a case of its own.
    // -----------------------------------------------------------------

    public function test_offering_an_image_publishes_it_and_detaching_makes_it_private()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();

        // Nothing embeds it, nothing offers it, no setting points at it.
        $this->get(route('images.show', $image))->assertForbidden();

        $download = $page->downloads()->create(['image_id' => $image->id]);

        $this->get(route('images.show', $image))->assertOk();
        $this->get(route('downloads.show', $download))->assertOk();

        $download->delete();

        $this->get(route('images.show', $image))->assertForbidden();
    }

    public function test_an_offered_image_on_a_protected_page_needs_the_unlock_cookie()
    {
        Storage::fake('local');
        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');
        $page = $this->makePage();
        $page->update(['access_password_id' => $password->id]);

        $image = $this->makeImage();
        $download = $page->downloads()->create(['image_id' => $image->id]);

        $this->get(route('downloads.show', $download))->assertForbidden();
        $this->get(route('images.show', $image))->assertForbidden();

        $this->withCookies($this->unlockCookie($password))
            ->get(route('downloads.show', $download))
            ->assertOk();

        $this->withCookies($this->unlockCookie($password))
            ->get(route('images.show', $image))
            ->assertOk();
    }

    public function test_a_protected_page_does_not_count_a_refused_download()
    {
        Storage::fake('local');
        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');
        $page = $this->makePage();
        $page->update(['access_password_id' => $password->id]);

        $download = $page->downloads()->create(['image_id' => $this->makeImage()->id]);

        $this->get(route('downloads.show', $download))->assertForbidden();

        $this->assertSame(0, $download->fresh()->downloads_count);
    }

    // -----------------------------------------------------------------
    // Serving. Identical to a document in every respect that matters:
    // the same single authorisation decision, the same tally, and always
    // an attachment.
    // -----------------------------------------------------------------

    public function test_the_counted_route_serves_an_image_as_an_attachment_and_counts_it()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create(['image_id' => $this->makeImage()->id]);

        // Never inline: this is the downloads section, where the point is to
        // end up with the file. The body's embed is what shows it in place.
        $this->get(route('downloads.show', $download))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=poster.webp');

        $this->assertSame(1, $download->fresh()->downloads_count);
    }

    public function test_the_owner_previewing_an_offered_image_does_not_inflate_the_tally()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create(['image_id' => $this->makeImage()->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('downloads.show', $download))
            ->assertOk();

        $this->assertSame(0, $download->fresh()->downloads_count);
    }

    /**
     * An SVG must not be renderable in this origin from *any* route. The
     * download route forces an attachment for everything, so the sandbox is
     * belt to those braces — but one of the two routes quietly not saying so
     * is how the rule stops being true.
     */
    public function test_an_offered_svg_is_still_sandboxed()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $svg = $this->makeImage('kaart.svg', 'image/svg+xml');
        $download = $page->downloads()->create(['image_id' => $svg->id]);

        $this->get(route('downloads.show', $download))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=kaart.svg')
            ->assertHeader(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; sandbox"
            );
    }

    public function test_an_offered_image_renders_as_a_download_card_on_the_public_page()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $download = $page->downloads()->create([
            'image_id' => $this->makeImage()->id,
            'label' => 'Posterversie',
        ]);

        $this->get('/natuurkunde/de-planeten')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->component('content/page')
                ->has('downloadGroups', 1)
                ->where('downloadGroups.0.downloads.0.label', 'Posterversie')
                // `images` has no `kind` column; the library it lives in is
                // the answer, and the icon on the card is the only thing that
                // reads it.
                ->where('downloadGroups.0.downloads.0.kind', 'image')
                ->where('downloadGroups.0.downloads.0.filename', 'poster.webp')
                ->where('downloadGroups.0.downloads.0.href', route('downloads.show', $download))
            );
    }

    // -----------------------------------------------------------------
    // Deleting. Blocks and says which pages, like every other reference
    // to uploaded media in this schema.
    // -----------------------------------------------------------------

    public function test_an_image_offered_as_a_download_cannot_be_deleted_and_names_the_page()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();
        $page->downloads()->create(['image_id' => $image->id]);

        $response = $this->actingAs(User::factory()->create())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.images.destroy', $image));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('De Planeten', session('error'));
        $this->assertStringContainsString(__('admin.downloads.offered_on'), session('error'));
        $this->assertModelExists($image);
        Storage::disk('local')->assertExists($image->path);
    }

    // -----------------------------------------------------------------
    // The admin endpoint.
    // -----------------------------------------------------------------

    public function test_the_admin_can_offer_an_image_as_a_download()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();
        $level = EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 0]);

        $this->actingAs(User::factory()->create())
            ->from(route('admin.pages.edit', $page))
            ->post(route('admin.pages.downloads.store', $page), [
                'image_id' => $image->id,
                'media_file_id' => null,
                'label' => 'Posterversie',
                'education_levels' => [$level->id],
            ])
            ->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHasNoErrors();

        $download = $page->downloads()->firstOrFail();

        $this->assertSame($image->id, $download->image_id);
        $this->assertNull($download->media_file_id);
        $this->assertSame([$level->id], $download->educationLevels->pluck('id')->all());
    }

    public function test_attaching_neither_or_both_is_a_form_error_rather_than_a_constraint_violation()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.pages.downloads.store', $page), [
                'media_file_id' => null,
                'image_id' => null,
            ])
            ->assertSessionHasErrors(['media_file_id', 'image_id']);

        $this->actingAs($user)
            ->post(route('admin.pages.downloads.store', $page), [
                'media_file_id' => $this->makeFile()->id,
                'image_id' => $this->makeImage()->id,
            ])
            ->assertSessionHasErrors(['media_file_id', 'image_id']);

        $this->assertSame(0, $page->downloads()->count());
    }

    public function test_the_same_image_cannot_be_offered_twice_through_the_endpoint()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();
        $page->downloads()->create(['image_id' => $image->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.downloads.store', $page), ['image_id' => $image->id])
            ->assertSessionHasErrors('image_id');
    }

    /**
     * The honest case the queue entry names: one picture, embedded in the
     * lesson *and* offered as a printable handout. An authored "this one is
     * for downloading" flag would have forced the owner to choose a lie.
     */
    public function test_one_image_can_be_both_embedded_and_offered()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();

        $page->writeContent([
            'type' => 'doc',
            'content' => [[
                'type' => 'imageAside',
                'attrs' => ['ulid' => $image->ulid, 'side' => 'right', 'size' => 'medium'],
            ]],
        ]);
        $download = $page->downloads()->create(['image_id' => $image->id]);

        $this->assertSame(1, $page->mediaReferences()->count());
        $this->assertSame(1, $page->downloads()->count());

        // Removing the body embed must not un-offer it: page_media_references
        // is derived data rebuilt wholesale on every save, and a download
        // folded in there would be deleted by the next edit.
        $page->writeContent(['type' => 'doc', 'content' => []]);

        $this->assertSame(0, $page->mediaReferences()->count());
        $this->assertSame(1, $page->downloads()->count());
        $this->get(route('images.show', $image))->assertOk();
        $this->get(route('downloads.show', $download))->assertOk();
    }

    public function test_duplicating_a_page_copies_an_offered_image()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $image = $this->makeImage();
        $page->downloads()->create(['image_id' => $image->id, 'label' => 'Poster']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.duplicate', $page))
            ->assertRedirect();

        $copy = Page::query()->where('slug', 'de-planeten-kopie')->firstOrFail();
        $copied = $copy->downloads()->firstOrFail();

        $this->assertSame($image->id, $copied->image_id);
        $this->assertNull($copied->media_file_id);
        $this->assertSame('Poster', $copied->label);
    }
}
