<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Search;

/**
 * Relevance of one indexed page for a set of folded terms.
 *
 * Weights: title equals the query 100, term in the title 50, term in the main heading 30, term in the
 * meta title/keywords/description 20, every occurrence in the text 5 (at most 20 occurrences per term).
 * A page about the thing ranks above a page that mentions it once in the ninth paragraph.
 */
class Ranking
{
    public const TITLE_EXACT = 100;
    public const TITLE = 50;
    public const HEADING = 30;
    public const META = 20;
    public const BODY = 5;
    public const BODY_CAP = 20;

    /**
     * @param array<string, string> $row folded fields: title_f, heading_f, meta_f, body_f
     * @param array<int, string> $terms folded terms
     * @return array{score: int, matched: int} matched = how many terms occur anywhere
     */
    public function score(array $row, array $terms, string $phrase = ''): array
    {
        $title = (string) ($row['title_f'] ?? '');
        $heading = (string) ($row['heading_f'] ?? '');
        $meta = (string) ($row['meta_f'] ?? '');
        $body = (string) ($row['body_f'] ?? '');

        $score = 0;
        $matched = 0;
        if ($phrase !== '' && trim($title) === $phrase) {
            $score += self::TITLE_EXACT;
        }

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }
            $hit = false;
            if (str_contains($title, $term)) {
                $score += self::TITLE;
                $hit = true;
            }
            if (str_contains($heading, $term)) {
                $score += self::HEADING;
                $hit = true;
            }
            if (str_contains($meta, $term)) {
                $score += self::META;
                $hit = true;
            }
            $count = substr_count($body, $term);
            if ($count > 0) {
                $score += self::BODY * min($count, self::BODY_CAP);
                $hit = true;
            }
            $matched += $hit ? 1 : 0;
        }

        return ['score' => $score, 'matched' => $matched];
    }

    /**
     * How many of the terms a page must contain in the fallback ("any") pass, when no page contains all.
     * One or two terms: all of them (two unrelated words should not fill the block with noise);
     * three or more: at least half, and at least two.
     */
    public function requiredInFallback(int $termCount): int
    {
        if ($termCount <= 2) {
            return $termCount;
        }

        return max(2, (int) ceil($termCount / 2));
    }
}
