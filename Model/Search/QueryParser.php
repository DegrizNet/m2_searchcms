<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Search;

use Magelan\SearchCms\Model\Text\Folder;

/**
 * Splits a search query into folded terms.
 *
 * - Anything that is not a letter or a digit separates terms, so extra spaces, "dostava - cena" or
 *   "HP-652" never produce an empty term (the 1.x bug: an empty term matched every page).
 * - Terms shorter than 2 characters and stop words ("in", "za", "the", "und" ...) do not count, unless
 *   the whole query consists of them.
 * - At most 8 terms, each at most 64 characters; duplicates are removed.
 * - Terms are returned in their matching form (see stem()).
 */
class QueryParser
{
    public const MAX_TERMS = 8;

    public const MAX_TERM_LENGTH = 64;

    private const MIN_TERM_LENGTH = 2;

    /**
     * Folded stop words: Slovenian, Croatian, English, German.
     */
    private const STOP_WORDS = [
        // sl / hr
        'in', 'za', 'na', 'je', 'se', 'ali', 'ki', 'da', 'ne', 'pa', 'po', 'iz', 'od', 'do', 'so', 'pri', 'ter',
        'ili', 'sa', 'su', 'te',
        // en
        'the', 'and', 'or', 'of', 'to', 'on', 'for', 'an', 'is', 'at', 'by', 'with', 'are',
        // de
        'der', 'die', 'das', 'und', 'oder', 'im', 'zu', 'mit', 'ist', 'ein', 'eine', 'von', 'den', 'dem', 'des',
    ];

    public function __construct(private readonly Folder $folder)
    {
    }

    /**
     * @return array<int, string> folded terms
     */
    public function parse(string $query): array
    {
        $folded = $this->folder->fold(trim($query));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $all = [];
        foreach ($parts as $part) {
            if (mb_strlen($part, 'UTF-8') > self::MAX_TERM_LENGTH) {
                continue;
            }
            $all[$part] = $part;
        }

        $counted = array_filter(
            $all,
            static fn (string $term): bool => mb_strlen($term, 'UTF-8') >= self::MIN_TERM_LENGTH
                && !in_array($term, self::STOP_WORDS, true)
        );

        if (!$counted) {
            // The whole query is stop words ("za", "in"): search for them after all, but never for
            // single characters, which would match nearly every page.
            $counted = array_filter(
                $all,
                static fn (string $term): bool => mb_strlen($term, 'UTF-8') >= self::MIN_TERM_LENGTH
            );
        }

        $terms = [];
        foreach (array_slice(array_values($counted), 0, self::MAX_TERMS) as $term) {
            $stem = $this->stem($term);
            $terms[$stem] = $stem;
        }

        return array_values($terms);
    }

    /**
     * Light stemming for inflected languages: a term of 5+ characters ending in a vowel loses that vowel,
     * so "dostava" also finds "dostavo", "dostave", "dostavi" and "pogoji" finds "pogojev". Terms are matched
     * as substrings, so the shortened form still finds the word itself.
     */
    public function stem(string $term): string
    {
        if (mb_strlen($term, 'UTF-8') >= 5 && preg_match('/[aeiou]$/', $term) === 1) {
            return substr($term, 0, -1);
        }

        return $term;
    }

    /**
     * Folded query as one phrase (terms joined by a space), for the "title equals the query" bonus.
     */
    public function phrase(string $query): string
    {
        $folded = $this->folder->fold(trim($query));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', $parts);
    }
}
