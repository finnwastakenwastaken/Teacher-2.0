<?php

namespace Tests\Feature\Content;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use App\Support\PageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Page bodies are TipTap JSON, never HTML, and the JSON is whitelisted
 * server-side. Storing JSON only removes stored XSS as a category if the
 * document itself is constrained — the browser is what sends it.
 */
class PageContentTest extends TestCase
{
    use RefreshDatabase;

    private function doc(array $content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    private function paragraph(string $text, array $marks = []): array
    {
        $node = ['type' => 'text', 'text' => $text];

        if ($marks !== []) {
            $node['marks'] = $marks;
        }

        return ['type' => 'paragraph', 'content' => [$node]];
    }

    private function makePage(): Page
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        return Page::query()->create([
            'title' => 'De Planeten',
            'slug' => 'de-planeten',
            'topic_id' => $topic->id,
        ]);
    }

    public function test_ordinary_formatting_survives_sanitising()
    {
        $document = $this->doc([
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Zwaartekracht']]],
            $this->paragraph('Vet', [['type' => 'bold']]),
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [$this->paragraph('Punt een')]],
            ]],
        ]);

        $clean = PageContent::sanitise($document);

        $this->assertSame($document, $clean);
    }

    public function test_an_unknown_node_type_is_dropped()
    {
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]],
            $this->paragraph('Blijft staan'),
        ]));

        $this->assertCount(1, $clean['content']);
        $this->assertSame('paragraph', $clean['content'][0]['type']);
    }

    public function test_an_unknown_mark_is_dropped_but_the_text_survives()
    {
        $clean = PageContent::sanitise($this->doc([
            $this->paragraph('Leesbaar', [['type' => 'onmouseover']]),
        ]));

        $this->assertSame('Leesbaar', $clean['content'][0]['content'][0]['text']);
        $this->assertArrayNotHasKey('marks', $clean['content'][0]['content'][0]);
    }

    public function test_a_javascript_link_is_refused_but_keeps_its_text()
    {
        $clean = PageContent::sanitise($this->doc([
            $this->paragraph('Klik hier', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]),
        ]));

        $textNode = $clean['content'][0]['content'][0];
        $this->assertSame('Klik hier', $textNode['text']);
        $this->assertArrayNotHasKey('marks', $textNode);
    }

    public function test_a_data_uri_link_is_refused()
    {
        $clean = PageContent::sanitise($this->doc([
            $this->paragraph('x', [['type' => 'link', 'attrs' => ['href' => 'data:text/html;base64,PHNjcmlwdD4=']]]),
        ]));

        $this->assertArrayNotHasKey('marks', $clean['content'][0]['content'][0]);
    }

    public function test_ordinary_links_are_kept()
    {
        foreach (['https://example.nl/x', 'http://example.nl', 'mailto:docent@school.nl', '/natuurkunde'] as $href) {
            $clean = PageContent::sanitise($this->doc([
                $this->paragraph('x', [['type' => 'link', 'attrs' => ['href' => $href]]]),
            ]));

            $this->assertSame(
                $href,
                $clean['content'][0]['content'][0]['marks'][0]['attrs']['href'],
                "Expected {$href} to be allowed."
            );
        }
    }

    public function test_a_protocol_relative_link_is_refused()
    {
        $clean = PageContent::sanitise($this->doc([
            $this->paragraph('x', [['type' => 'link', 'attrs' => ['href' => '//evil.example/x']]]),
        ]));

        $this->assertArrayNotHasKey('marks', $clean['content'][0]['content'][0]);
    }

    public function test_an_unexpected_attribute_is_stripped()
    {
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'heading', 'attrs' => ['level' => 2, 'onclick' => 'alert(1)'], 'content' => []],
        ]));

        $this->assertSame(['level' => 2], $clean['content'][0]['attrs']);
    }

    public function test_an_embed_with_a_malformed_identifier_is_dropped()
    {
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => '../../etc/passwd']],
            ['type' => 'youtubeEmbed', 'attrs' => ['videoId' => '"><script>']],
            $this->paragraph('Blijft staan'),
        ]));

        $this->assertCount(1, $clean['content']);
        $this->assertSame('paragraph', $clean['content'][0]['type']);
    }

    public function test_a_heading_level_outside_the_allowed_range_is_clamped()
    {
        // h1 belongs to the page title alone.
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => []],
        ]));

        $this->assertSame(2, $clean['content'][0]['attrs']['level']);
    }

    public function test_plain_text_is_derived_from_the_document()
    {
        $text = PageContent::toPlainText($this->doc([
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Zwaartekracht']]],
            $this->paragraph('Valt naar beneden.'),
        ]));

        $this->assertSame('Zwaartekracht Valt naar beneden.', $text);
    }

    public function test_saving_content_stores_the_sanitised_document_and_its_plain_text()
    {
        $page = $this->makePage();

        $page->writeContent($this->doc([
            ['type' => 'script', 'content' => []],
            $this->paragraph('Zichtbare tekst'),
        ]));

        $page->refresh();

        $this->assertCount(1, $page->content['content']);
        $this->assertSame('Zichtbare tekst', $page->content_text);
    }

    public function test_guests_cannot_save_page_content()
    {
        $page = $this->makePage();

        $this->put(route('admin.pages.content.update', $page), ['content' => $this->doc([])])
            ->assertRedirect(route('login'));
    }

    public function test_the_admin_can_save_page_content_over_http()
    {
        $page = $this->makePage();

        $this->actingAs(User::factory()->create())
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.content.update', $page), [
                'content' => $this->doc([$this->paragraph('Hallo')]),
            ])
            ->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHas('status');

        $this->assertSame('Hallo', $page->fresh()->content_text);
    }

    public function test_embedding_a_file_publishes_it_to_anonymous_visitors()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);

        // Uploaded but not referenced: private.
        $this->get(route('media.show', $file))->assertForbidden();

        $page->writeContent($this->doc([
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => $file->ulid]],
        ]));

        // Referenced by a page: published.
        $this->get(route('media.show', $file))->assertOk();
    }

    public function test_removing_an_embed_makes_the_file_private_again()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $path = 'images/2026/08/'.Str::ulid().'.png';
        Storage::disk('local')->put($path, 'bytes');
        $image = Image::query()->create([
            'path' => $path,
            'alt_text' => 'Een diagram',
            'size_bytes' => 5,
            'mime' => 'image/png',
            'original_filename' => 'diagram.png',
        ]);

        $page->writeContent($this->doc([
            ['type' => 'imageGallery', 'attrs' => ['ulids' => [$image->ulid]]],
        ]));
        $this->get(route('images.show', $image))->assertOk();

        $page->writeContent($this->doc([$this->paragraph('Geen afbeelding meer')]));

        $this->get(route('images.show', $image))->assertForbidden();
        $this->assertSame(0, $page->mediaReferences()->count());
    }

    public function test_media_on_a_hidden_page_still_serves()
    {
        Storage::fake('local');
        $page = $this->makePage();
        $page->update(['is_hidden' => true]);

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'concept.pdf',
        ]);

        $page->writeContent($this->doc([
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => $file->ulid]],
        ]));

        // A hidden page is a draft, not a secret — it still renders at its
        // direct URL, so refusing its media would render it broken instead
        // of private. Secrecy is what page passwords are for.
        $this->get(route('media.show', $file))->assertOk();
    }

    public function test_an_embedded_file_cannot_be_deleted_and_says_which_page_uses_it()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);

        $page->writeContent($this->doc([
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => $file->ulid]],
        ]));

        $this->actingAs(User::factory()->create())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.files.destroy', $file))
            ->assertSessionHas('error');

        $this->assertModelExists($file);
        Storage::disk('local')->assertExists($path);
    }

    public function test_deleting_a_page_releases_its_media_references()
    {
        Storage::fake('local');
        $page = $this->makePage();

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);

        $page->writeContent($this->doc([
            ['type' => 'fileEmbed', 'attrs' => ['ulid' => $file->ulid]],
        ]));

        $page->delete();

        // The reference rows cascade with the page, so the file stops being
        // published and becomes deletable again.
        $this->assertSame(0, $file->pageReferences()->count());
        $this->get(route('media.show', $file))->assertForbidden();
    }
}
