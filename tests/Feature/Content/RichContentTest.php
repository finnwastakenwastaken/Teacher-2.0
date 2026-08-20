<?php

namespace Tests\Feature\Content;

use App\Support\PageContent;
use Tests\TestCase;

/**
 * The editor's second wave of node types: tables, subscript/superscript and
 * text alignment.
 *
 * Each of these is a whitelist entry in App\Support\PageContent, a case in
 * components/content/rich-text.tsx and a toolbar button. The whitelist is
 * what these tests pin — a node type the editor can make but the whitelist
 * does not know about silently stops saving, which is the safe direction to
 * fail and an unpleasant one to debug.
 */
class RichContentTest extends TestCase
{
    public function test_subscript_and_superscript_marks_survive()
    {
        $clean = PageContent::sanitise($this->doc([
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'H'],
                    ['type' => 'text', 'text' => '2', 'marks' => [['type' => 'subscript']]],
                    ['type' => 'text', 'text' => 'O, en '],
                    ['type' => 'text', 'text' => '2', 'marks' => [['type' => 'superscript']]],
                ],
            ],
        ]));

        $children = $clean['content'][0]['content'];

        $this->assertSame('subscript', $children[1]['marks'][0]['type']);
        $this->assertSame('superscript', $children[3]['marks'][0]['type']);
    }

    public function test_a_table_round_trips_whole()
    {
        $clean = PageContent::sanitise($this->doc([$this->table()]));

        $table = $clean['content'][0];

        $this->assertSame('table', $table['type']);
        $this->assertSame('tableRow', $table['content'][0]['type']);
        $this->assertSame('tableHeader', $table['content'][0]['content'][0]['type']);
        $this->assertSame('tableCell', $table['content'][1]['content'][0]['type']);
        $this->assertSame(
            'Stof',
            $table['content'][0]['content'][0]['content'][0]['content'][0]['text']
        );
    }

    public function test_cell_spans_and_column_widths_survive()
    {
        $clean = PageContent::sanitise($this->doc([[
            'type' => 'table',
            'content' => [[
                'type' => 'tableRow',
                'content' => [[
                    'type' => 'tableCell',
                    'attrs' => ['colspan' => 2, 'rowspan' => 3, 'colwidth' => [120, 240]],
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]],
                ]],
            ]],
        ]]));

        $attrs = $clean['content'][0]['content'][0]['content'][0]['attrs'];

        $this->assertSame(2, $attrs['colspan']);
        $this->assertSame(3, $attrs['rowspan']);
        $this->assertSame([120, 240], $attrs['colwidth']);
    }

    /**
     * TipTap writes an explicit null for a column nobody resized. That is a
     * value, not a malformed attribute, so the cell has to survive it.
     */
    public function test_a_null_column_width_keeps_the_cell()
    {
        $clean = PageContent::sanitise($this->doc([[
            'type' => 'table',
            'content' => [[
                'type' => 'tableRow',
                'content' => [[
                    'type' => 'tableCell',
                    'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]],
                ]],
            ]],
        ]]));

        $cell = $clean['content'][0]['content'][0]['content'][0];

        $this->assertSame('tableCell', $cell['type']);
        $this->assertArrayNotHasKey('colwidth', $cell['attrs']);
    }

    /**
     * A merged cell drawn unmerged is a cosmetic loss; a dropped cell is a
     * hole in the table. So a nonsense span falls back rather than rejecting.
     */
    public function test_a_nonsense_span_falls_back_to_one_and_keeps_the_cell()
    {
        $clean = PageContent::sanitise($this->doc([[
            'type' => 'table',
            'content' => [[
                'type' => 'tableRow',
                'content' => [[
                    'type' => 'tableCell',
                    'attrs' => ['colspan' => 999999, 'rowspan' => '3', 'colwidth' => ['huge']],
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]],
                ]],
            ]],
        ]]));

        $cell = $clean['content'][0]['content'][0]['content'][0];

        $this->assertSame(1, $cell['attrs']['colspan']);
        $this->assertSame(1, $cell['attrs']['rowspan']);
        $this->assertArrayNotHasKey('colwidth', $cell['attrs']);
    }

    public function test_text_alignment_survives_on_paragraphs_and_headings()
    {
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => [['type' => 'text', 'text' => 'a']]],
            ['type' => 'heading', 'attrs' => ['level' => 3, 'textAlign' => 'right'], 'content' => [['type' => 'text', 'text' => 'b']]],
        ]));

        $this->assertSame('center', $clean['content'][0]['attrs']['textAlign']);
        $this->assertSame('right', $clean['content'][1]['attrs']['textAlign']);
        $this->assertSame(3, $clean['content'][1]['attrs']['level']);
    }

    public function test_an_unaligned_paragraph_carries_no_alignment_attribute()
    {
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'paragraph', 'attrs' => ['textAlign' => null], 'content' => [['type' => 'text', 'text' => 'a']]],
        ]));

        $this->assertArrayNotHasKey('attrs', $clean['content'][0]);
    }

    public function test_an_invented_alignment_falls_back_to_left()
    {
        $clean = PageContent::sanitise($this->doc([
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'javascript:alert(1)'], 'content' => [['type' => 'text', 'text' => 'a']]],
        ]));

        $this->assertSame('left', $clean['content'][0]['attrs']['textAlign']);
    }

    /**
     * The whitelist still applies inside a cell — a table is not a hole in it.
     */
    public function test_the_whitelist_still_applies_inside_a_cell()
    {
        $clean = PageContent::sanitise($this->doc([[
            'type' => 'table',
            'content' => [[
                'type' => 'tableRow',
                'content' => [[
                    'type' => 'tableCell',
                    'content' => [
                        ['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]],
                        [
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Klik', 'marks' => [
                                ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
                            ]]],
                        ],
                    ],
                ]],
            ]],
        ]]));

        $cell = $clean['content'][0]['content'][0]['content'][0];

        $this->assertCount(1, $cell['content']);
        $this->assertSame('paragraph', $cell['content'][0]['type']);
        $this->assertArrayNotHasKey('marks', $cell['content'][0]['content'][0]);
    }

    public function test_the_plain_text_derivation_reaches_into_tables()
    {
        $text = PageContent::toPlainText($this->doc([$this->table()]));

        $this->assertStringContainsString('Stof', $text);
        $this->assertStringContainsString('Water', $text);
    }

    /**
     * A topic introduction and the homepage use the same whitelist minus the
     * embeds — tables and sub/superscript are text, so they stay.
     */
    public function test_tables_and_scripts_survive_the_without_embeds_whitelist()
    {
        $clean = PageContent::sanitiseWithoutEmbeds($this->doc([
            $this->table(),
            ['type' => 'youtubeEmbed', 'attrs' => ['videoId' => 'QO4uodTbdgI']],
        ]));

        $this->assertCount(1, $clean['content']);
        $this->assertSame('table', $clean['content'][0]['type']);
    }

    private function doc(array $content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    private function table(): array
    {
        return [
            'type' => 'table',
            'content' => [
                [
                    'type' => 'tableRow',
                    'content' => [
                        $this->cell('tableHeader', 'Stof'),
                        $this->cell('tableHeader', 'Kookpunt'),
                    ],
                ],
                [
                    'type' => 'tableRow',
                    'content' => [
                        $this->cell('tableCell', 'Water'),
                        $this->cell('tableCell', '100 °C'),
                    ],
                ],
            ],
        ];
    }

    private function cell(string $type, string $text): array
    {
        return [
            'type' => $type,
            'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null],
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
        ];
    }
}
