<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Search;

use Magelan\SearchCms\Model\Text\Folder;

/**
 * Builds the text excerpt under a result: about 160 characters around the first occurrence of a term,
 * HTML escaped, every word that contains a term wrapped in <mark>. Without an occurrence in the text (the page
 * matched by its title) the excerpt is the start of the text, without a mark.
 */
class Snippet
{
    public const LENGTH = 160;

    /** Characters shown before the first match, so it is not glued to the start of the excerpt. */
    private const LEAD = 40;

    private const ELLIPSIS = '…';

    public function __construct(private readonly Folder $folder)
    {
    }

    /**
     * @param array<int, string> $terms folded terms
     * @return string HTML (escaped text and <mark> elements only)
     */
    public function build(string $text, array $terms, int $length = self::LENGTH): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        [$folded, $map, $chars] = $this->folder->foldWithMap($text);
        $charCount = count($chars);

        // Character ranges [start, end) of every occurrence of every term.
        $ranges = [];
        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }
            $offset = 0;
            while (($pos = strpos($folded, $term, $offset)) !== false) {
                $start = $map[$pos];
                $end = $map[$pos + strlen($term) - 1] + 1;
                // Mark the whole word: "dostav" (from "dostava") marks all of "dostavo".
                while ($start > 0 && $this->isWordChar($chars[$start - 1])) {
                    $start--;
                }
                while ($end < $charCount && $this->isWordChar($chars[$end])) {
                    $end++;
                }
                $ranges[] = [$start, $end];
                $offset = $pos + strlen($term);
            }
        }
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $b[1] <=> $a[1]);

        $windowStart = 0;
        if ($ranges && $charCount > $length) {
            $windowStart = max(0, $ranges[0][0] - self::LEAD);
            $windowStart = min($windowStart, max(0, $charCount - $length));
            $windowStart = $this->wordStart($chars, $windowStart);
        }
        $windowEnd = min($charCount, $windowStart + $length);
        if ($windowEnd < $charCount) {
            $windowEnd = $this->wordEnd($chars, $windowStart, $windowEnd);
        }

        $html = '';
        $cursor = $windowStart;
        foreach ($ranges as [$start, $end]) {
            if ($end <= $cursor || $start >= $windowEnd) {
                continue;
            }
            $start = max($start, $cursor);
            $end = min($end, $windowEnd);
            $html .= $this->escape(array_slice($chars, $cursor, $start - $cursor));
            $html .= '<mark>' . $this->escape(array_slice($chars, $start, $end - $start)) . '</mark>';
            $cursor = $end;
        }
        $html .= $this->escape(array_slice($chars, $cursor, $windowEnd - $cursor));

        $html = trim($html);
        if ($windowStart > 0) {
            $html = self::ELLIPSIS . ' ' . $html;
        }
        if ($windowEnd < $charCount) {
            $html = rtrim($html, ' ,;:') . ' ' . self::ELLIPSIS;
        }

        return $html;
    }

    private function isWordChar(string $char): bool
    {
        return preg_match('/^[\p{L}\p{N}]$/u', $char) === 1;
    }

    /**
     * @param array<int, string> $chars
     */
    private function escape(array $chars): string
    {
        return htmlspecialchars(implode('', $chars), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Moves a window start forward to the beginning of a word (at most 20 characters).
     *
     * @param array<int, string> $chars
     */
    private function wordStart(array $chars, int $start): int
    {
        if ($start === 0 || $chars[$start - 1] === ' ') {
            return $start;
        }
        for ($i = $start, $limit = min(count($chars), $start + 20); $i < $limit; $i++) {
            if ($chars[$i] === ' ') {
                return $i + 1;
            }
        }

        return $start;
    }

    /**
     * Moves a window end back to the end of a word (at most 20 characters).
     *
     * @param array<int, string> $chars
     */
    private function wordEnd(array $chars, int $start, int $end): int
    {
        for ($i = $end, $limit = max($start + 1, $end - 20); $i > $limit; $i--) {
            if ($chars[$i] === ' ') {
                return $i;
            }
        }

        return $end;
    }
}
