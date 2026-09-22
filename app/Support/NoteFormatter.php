<?php

namespace App\Support;

use App\Support\Links\LinkResolver;


class NoteFormatter
{
    private const TOKENS = '/(```[\s\S]*?```|`[^`\n]+`)/';

    private const FENCE_LANGUAGES = [
        'bash', 'c', 'cpp', 'c++', 'cs', 'css', 'diff', 'dockerfile', 'go', 'html', 'ini',
        'java', 'javascript', 'js', 'json', 'jsx', 'kotlin', 'lua', 'makefile', 'markdown',
        'md', 'mysql', 'nginx', 'php', 'python', 'py', 'r', 'ruby', 'rb', 'rust', 'rs',
        'scss', 'sh', 'shell', 'sql', 'swift', 'text', 'toml', 'ts', 'tsx', 'typescript',
        'vim', 'xml', 'yaml', 'yml', 'zsh',
    ];

    public function __construct(private LinkResolver $links)
    {
    }

    public function render(string $text): string
    {
        $this->links->assertPrimed();

        $tokens = preg_split(self::TOKENS, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';

        foreach ($tokens as $index => $token) {
            $isCode = $index % 2 === 1;

            if (! $isCode) {
                $out .= $this->plain($this->trimAroundFences($tokens, $index));
                continue;
            }

            $out .= str_starts_with($token, '```')
                ? $this->fence($token)
                : $this->inlineCode($token);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function trimAroundFences(array $tokens, int $index): string
    {
        $text = $tokens[$index];

        if (str_starts_with($tokens[$index - 1] ?? '', '```')) {
            $text = preg_replace('/^\R/', '', $text, 1);
        }

        if (str_starts_with($tokens[$index + 1] ?? '', '```')) {
            $text = preg_replace('/\R\z/', '', $text, 1);
        }

        return $text;
    }

    private function plain(string $text): string
    {
        $html = $this->escape($text);

        foreach ($this->links->handlers() as $handler) {
            $html = preg_replace_callback(
                $handler->pattern(),
                function (array $match) use ($handler) {
                    $chip = $this->links->chip($handler->type(), $match[1]);
                    return $chip === null ? $match[0] : $this->chip($chip);
                },
                $html,
            );
        }

        return $html;
    }

    /** @param array{label: string, url: string, icon: string} $chip */
    private function chip(array $chip): string
    {
        return '<a class="xn-link" href="' . $this->escape($chip['url']) . '" dir="auto">'
            . '<i class="' . $this->escape($chip['icon']) . '" aria-hidden="true"></i>'
            . '<span>' . $this->escape($chip['label']) . '</span>'
            . '</a>';
    }

    private function fence(string $token): string
    {
        $inner = substr($token, 3, -3);
        if (preg_match('/^([A-Za-z0-9+#._-]{1,12})\R/', $inner, $match)
            && in_array(strtolower($match[1]), self::FENCE_LANGUAGES, true)) {
            $inner = substr($inner, strlen($match[0]));
        }

        $inner = preg_replace('/^\R|\R\z/', '', $inner);

        return '<pre class="xn-code"><code>' . $this->escape((string) $inner) . '</code></pre>';
    }

    private function inlineCode(string $token): string
    {
        return '<code class="xn-code-inline">' . $this->escape(substr($token, 1, -1)) . '</code>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
