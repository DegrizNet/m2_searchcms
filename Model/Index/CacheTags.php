<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Index;

use Magento\Framework\DataObject\IdentityInterface;

/**
 * Carries cache tags to the "clean_cache_by_tags" event (cleans the full page cache and Varnish).
 */
class CacheTags implements IdentityInterface
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(private readonly array $tags)
    {
    }

    /**
     * @return array<int, string>
     */
    public function getIdentities()
    {
        return $this->tags;
    }
}
