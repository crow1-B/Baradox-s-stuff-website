<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every element that transitions a colour must stop doing it while theme.js holds
 * data-phase-snap on the root (tokens.css). A colour transition left off the lists starts on
 * every phase change, and starting one on each button of a long page is what made `t` and the
 * clock's cross-fade slow.
 */
class PhaseSnapTest extends TestCase
{
    private const COLOUR = '/^(color|background|background-color|border-color|border-(top|right|bottom|left)-color|box-shadow|outline-color|fill|stroke|text-decoration-color|all)$/';

    // The login page has no phase changes (a fixed dusk), and tokens.css's own transition is
    // the cross-fade itself.
    private const EXEMPT = ['login.css', 'tokens.css'];

    public function test_every_colour_transition_is_snapped_during_a_phase_change(): void
    {
        $files = glob(resource_path('css/*.css'));
        $snapped = [];
        $missing = [];

        foreach ($files as $file) {
            $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($file));
            preg_match_all('/:root\[data-phase-snap\]\s*:is\(([^)]*)\)/', $css, $m);
            $snapped[basename($file)] = array_map('trim', explode(',', implode(',', $m[1])));
        }

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, self::EXEMPT, true)) {
                continue;
            }
            $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($file));
            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as [, $selector, $body]) {
                if (str_contains($selector, 'data-phase-snap')) {
                    continue;
                }
                preg_match_all('/transition(?:-property)?\s*:\s*([^;]+)/', $body, $t);
                foreach ($t[1] as $value) {
                    $properties = array_map(fn ($part) => strtok(trim($part), ' '), explode(',', $value));
                    if (! array_filter($properties, fn ($p) => preg_match(self::COLOUR, $p))) {
                        continue;
                    }
                    foreach (explode(',', $selector) as $one) {
                        // The element the rule styles: its last compound, without states.
                        $parts = preg_split('/\s*[\s>+~]\s*/', trim($one));
                        $subject = preg_replace('/:{1,2}[\w-]+(\([^)]*\))?/', '', end($parts));
                        $covered = collect([$name, 'components.css'])->contains(
                            fn ($f) => collect($snapped[$f] ?? [])->contains(fn ($s) => $s === $subject || str_ends_with($s, ' ' . $subject) || str_ends_with($subject, $s))
                        );
                        if (! $covered) {
                            $missing[] = "{$name}: " . trim($one);
                        }
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Colour transitions not listed in their file's :root[data-phase-snap] rule");
    }
}
