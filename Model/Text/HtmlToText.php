<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Text;

/**
 * Turns CMS page HTML into plain text for the search list.
 *
 * Widget and block directives ({{widget ...}}, {{block ...}}, {{config ...}}) are removed, not executed:
 * executing them is expensive and can render customer or cart specific output. Text that a page pulls in
 * through a block or widget is therefore not searchable (documented in the README).
 *
 * Scripts, styles, SVG, templates and comments are removed with their content. Page Builder markup
 * (data-content-type attributes, wrappers) needs nothing special: its text sits in ordinary elements.
 * The Page Builder "HTML Code" element stores escaped HTML, so tags are stripped a second time after
 * decoding entities.
 */
class HtmlToText
{
    /**
     * Longest text kept per page (characters). Long legal pages stay searchable by their first part.
     */
    public const MAX_LENGTH = 20000;

    private const DROP_WITH_CONTENT = '#<(script|style|noscript|template|svg|iframe|object|head|select|textarea)\b[^>]*>.*?</\1\s*>#is';

    private const BLOCK_TAGS = '#<(/?)(p|div|br|li|ul|ol|h[1-6]|tr|td|th|table|section|article|header|footer|blockquote|dd|dt|figcaption|hr|address)\b[^>]*>#i';

    public function convert(?string $html, int $maxLength = self::MAX_LENGTH): string
    {
        $text = (string) $html;
        if (trim($text) === '') {
            return '';
        }

        $text = $this->strip($text);
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Escaped markup (Page Builder "HTML Code") becomes real tags after decoding: strip once more.
        if (preg_match('#<[a-z!/]#i', $decoded) === 1) {
            $decoded = html_entity_decode($this->strip($decoded), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $decoded = str_replace("\xC2\xA0", ' ', $decoded);
        $decoded = (string) preg_replace('/\s+/u', ' ', $decoded);
        $decoded = trim($decoded);

        if ($maxLength > 0 && mb_strlen($decoded, 'UTF-8') > $maxLength) {
            $decoded = mb_substr($decoded, 0, $maxLength, 'UTF-8');
        }

        return $decoded;
    }

    private function strip(string $text): string
    {
        // Directives first: {{widget type="..." template="..."}} may contain ">" characters.
        $text = (string) preg_replace('/\{\{.*?\}\}/s', ' ', $text);
        $text = (string) preg_replace('/<!--.*?-->/s', ' ', $text);
        $text = (string) preg_replace(self::DROP_WITH_CONTENT, ' ', $text);
        // Block level tags separate words ("<p>Dostava</p><p>Plačilo</p>" must not become "DostavaPlačilo").
        $text = (string) preg_replace(self::BLOCK_TAGS, ' ', $text);

        return strip_tags($text);
    }
}
