<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Api;

use Magelan\SearchCms\Api\Data\HitInterface;

/**
 * Public search over the CMS page list (and blog posts when "Search blog posts too" is on).
 *
 * Used by the search results block and meant for other modules, e.g. the "Pages" section of a search
 * suggestion drawer: call search($query, 3) instead of querying cms_page yourself.
 */
interface ContentSearchInterface
{
    /**
     * Best matches first. Respects the store's settings (excluded pages, blog, minimum text length);
     * returns an empty list when the module is disabled for the store or the query has no usable term.
     *
     * @param string $query what the shopper typed
     * @param int $limit 1..50
     * @param int|null $storeId null = current store view
     * @param bool $withSnippet false skips building excerpts (cheaper, e.g. for one line suggestions)
     * @return HitInterface[]
     */
    public function search(string $query, int $limit = 5, ?int $storeId = null, bool $withSnippet = true): array;
}
