<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Index;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Human readable state of the search list for the settings page ("Built 24 pages, last on ...").
 */
class StatusText
{
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * @param array{built_at: ?string, pages: int, posts: int, empty: int, errors: array<string, string>, rows: int} $status
     * @return array{built: bool, text: string, empty: string, errors: string}
     */
    public function describe(array $status): array
    {
        $built = $status['rows'] > 0 || $status['built_at'] !== null;
        if (!$built) {
            $text = (string) __('Not built');
        } elseif ($status['built_at'] !== null) {
            $date = $this->timezone->formatDateTime(
                $status['built_at'],
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT
            );
            $text = $status['posts'] > 0
                ? (string) __('Built %1 pages and %2 blog posts, last on %3', $status['pages'], $status['posts'], $date)
                : (string) __('Built %1 pages, last on %2', $status['pages'], $date);
        } else {
            $text = (string) __('Built %1 pages', $status['pages']);
        }

        return [
            'built' => $built,
            'text' => $text,
            'empty' => (string) $status['empty'],
            'errors' => $status['errors'] ? implode(', ', array_keys($status['errors'])) : '',
        ];
    }
}
