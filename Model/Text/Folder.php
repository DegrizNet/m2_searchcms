<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Text;

/**
 * Folds text for matching: lower case, diacritics removed ("Vračilo" -> "vracilo", "Größe" -> "grosse").
 *
 * The same folding is applied to the indexed text and to the search terms, so a shopper who types
 * without diacritics ("vracilo", "placilo") finds the pages written with them, and the other way round.
 */
class Folder
{
    /**
     * Letters that Unicode decomposition does not turn into a base letter plus accents.
     */
    private const SPECIAL = [
        'ß' => 'ss', 'ẞ' => 'ss', 'đ' => 'd', 'Đ' => 'd', 'ł' => 'l', 'Ł' => 'l', 'ø' => 'o', 'Ø' => 'o',
        'æ' => 'ae', 'Æ' => 'ae', 'œ' => 'oe', 'Œ' => 'oe', 'ı' => 'i', 'þ' => 'th', 'ð' => 'd',
    ];

    /**
     * Fallback when the intl extension is missing (Magento requires it, but tests may run without it).
     */
    private const LATIN = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'ď' => 'd',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ė' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ő' => 'o',
        'ō' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's', 'ť' => 't', 'ţ' => 't',
        'ț' => 't', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ž' => 'z', 'ż' => 'z',
    ];

    /**
     * @var array<string, string>
     */
    private array $charCache = [];

    /**
     * Folds a whole string.
     */
    public function fold(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (preg_match('/[^\x00-\x7F]/', $text) !== 1) {
            return strtolower($text);
        }

        return implode('', array_map([$this, 'foldChar'], mb_str_split($text, 1, 'UTF-8')));
    }

    /**
     * Folds one character (may return 0, 1 or 2 characters).
     */
    public function foldChar(string $char): string
    {
        if (isset($this->charCache[$char])) {
            return $this->charCache[$char];
        }
        if (strlen($char) === 1) {
            return $this->charCache[$char] = strtolower($char);
        }
        if (isset(self::SPECIAL[$char])) {
            return $this->charCache[$char] = self::SPECIAL[$char];
        }
        $lower = mb_strtolower($char, 'UTF-8');
        if (isset(self::SPECIAL[$lower])) {
            return $this->charCache[$char] = self::SPECIAL[$lower];
        }
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($lower, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $stripped = preg_replace('/\p{Mn}+/u', '', $decomposed);
                if (is_string($stripped) && $stripped !== '') {
                    return $this->charCache[$char] = $stripped;
                }
            }
        }

        return $this->charCache[$char] = self::LATIN[$lower] ?? $lower;
    }

    /**
     * Folds a string and returns, for every byte of the folded string, the index of the original
     * character it came from. Used to put <mark> around the original (unfolded) words.
     *
     * @return array{0: string, 1: array<int, int>, 2: array<int, string>} folded text, map, original chars
     */
    public function foldWithMap(string $text): array
    {
        $chars = mb_str_split($text, 1, 'UTF-8');
        $folded = '';
        $map = [];
        foreach ($chars as $index => $char) {
            $part = $this->foldChar($char);
            $folded .= $part;
            for ($i = 0, $n = strlen($part); $i < $n; $i++) {
                $map[] = $index;
            }
        }

        return [$folded, $map, $chars];
    }
}
