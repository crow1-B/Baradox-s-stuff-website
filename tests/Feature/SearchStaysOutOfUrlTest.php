<?php

namespace Tests\Feature;

use Tests\TestCase;

// Music's and the Diary's search boxes filter live and never write what was typed into the
// URL, on Enter or otherwise: the box can be claimed by another script (CLAUDE.md §4), so a
// query in the URL would put whatever was typed into history. A static check, like PhaseSnapTest.
class SearchStaysOutOfUrlTest extends TestCase
{
    private const FILES = ['music.js', 'diary.js'];

    public function test_the_search_query_is_never_written_to_the_url(): void
    {
        foreach (self::FILES as $name) {
            $js = preg_replace('#//[^\n]*|/\*.*?\*/#s', '', file_get_contents(resource_path('js/' . $name)));

            $this->assertDoesNotMatchRegularExpression('/\bset\(\s*[\'"]q[\'"]/', $js, "{$name} writes ?q=");
            $this->assertDoesNotMatchRegularExpression('/[?&]q=/', $js, "{$name} builds a ?q= URL");
            $this->assertStringNotContainsString(':search-sync', $js, "{$name} still offers an uncommitted query for the URL");
            $this->assertMatchesRegularExpression('/searchParams\.delete\(\s*[\'"]q[\'"]\s*\)/', $js, "{$name} no longer drops a leftover ?q=");
        }
    }

    public function test_enter_still_offers_the_query_to_listeners(): void
    {
        $this->assertStringContainsString("'music:search-commit'", file_get_contents(resource_path('js/music.js')));
        $this->assertStringContainsString("'diary:search-commit'", file_get_contents(resource_path('js/diary.js')));
    }
}
