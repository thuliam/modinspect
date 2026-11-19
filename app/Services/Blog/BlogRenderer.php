<?php
declare(strict_types=1);

namespace App\Services\Blog;

/**
 * LS-24 M8: turns an article body written in plain text into safe HTML. Pure. The body is HTML-escaped FIRST; only then are the few markers below turned into tags,
 * so no tag, attribute or script a writer types can reach the page.
 *   blank line = new paragraph          ## Heading / ### Heading        - item (consecutive lines = list)        > quote
 *   **bold**                            [text](https://example.com) or [text](/price)  (http, https or a path from the site root; nothing else becomes a link)
 *   {{price:model-slug}} on a line of its own = the live price card of that model (drawn by the caller; unknown or unpriced models draw their own notice)
 */
final class BlogRenderer
{
    /** @param callable(string):string $priceCard slug => HTML of the card (must escape its own content) */
    public static function render(string $body, callable $priceCard): string
    {
        $blocks = preg_split('/\n{2,}/', trim(str_replace(["\r\n", "\r"], "\n", $body))) ?: [];
        $out = [];
        foreach ($blocks as $block) {
            $block = trim($block, "\n");
            if ($block === '') { continue; }
            if (preg_match('/^\{\{price:([a-z0-9-]{1,190})\}\}$/', trim($block), $m) === 1) { $out[] = $priceCard($m[1]); continue; }
            if (preg_match('/^(#{2,3})\s+(.+)$/s', $block, $m) === 1 && !str_contains($m[2], "\n")) { $lvl = strlen($m[1]); $out[] = "<h$lvl>" . self::inline($m[2]) . "</h$lvl>"; continue; }
            $lines = explode("\n", $block);
            if (count(array_filter($lines, static fn(string $l): bool => preg_match('/^-\s+\S/', $l) === 1)) === count($lines)) {
                $out[] = '<ul>' . implode('', array_map(static fn(string $l): string => '<li>' . self::inline(preg_replace('/^-\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
                continue;
            }
            if (count(array_filter($lines, static fn(string $l): bool => str_starts_with($l, '>'))) === count($lines)) {
                $out[] = '<blockquote class="blockquote">' . implode('<br>', array_map(static fn(string $l): string => self::inline(ltrim(substr($l, 1))), $lines)) . '</blockquote>';
                continue;
            }
            $out[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
        }
        return implode("\n", $out);
    }

    /** escape, then bold and links */
    public static function inline(string $text): string
    {
        $t = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = (string)preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $t);
        return (string)preg_replace('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/u', '<a href="$2" rel="noopener">$1</a>', $t);
    }

    /** minutes to read: Thai text has no spaces, so characters are counted (about 900 a minute), at least 1 */
    public static function readingMinutes(string $body): int
    {
        return max(1, (int)ceil(mb_strlen(strip_tags($body), 'UTF-8') / 900));
    }

    /** a plain-text summary for lists and meta descriptions: markers removed, cut at a word-safe length */
    public static function summary(string $body, int $max = 160): string
    {
        $t = (string)preg_replace('/\{\{price:[a-z0-9-]+\}\}/', '', $body);
        $t = (string)preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $t);
        $t = trim((string)preg_replace('/[#>*\-\s]+/u', ' ', $t));
        return mb_strlen($t, 'UTF-8') > $max ? rtrim(mb_substr($t, 0, $max - 1, 'UTF-8')) . '…' : $t;
    }
}
