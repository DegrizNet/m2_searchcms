<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\ViewModel;

use Magelan\SearchCms\Api\ContentSearchInterface;
use Magelan\SearchCms\Api\Data\HitInterface;
use Magelan\SearchCms\Model\Config;
use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Cms\Model\Page;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data for the "Pages matching your search" block (Luma and Hyvä templates).
 */
class SearchResults implements ArgumentInterface
{
    /**
     * @var array<int, HitInterface>|null
     */
    private ?array $hits = null;

    public function __construct(
        private readonly ContentSearchInterface $contentSearch,
        private readonly Config $config,
        private readonly QueryFactory $queryFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled($this->storeId());
    }

    public function getQueryText(): string
    {
        return trim((string) $this->queryFactory->get()->getQueryText());
    }

    /**
     * All hits shown in the block (visible rows and the ones under "Show all").
     *
     * @return array<int, HitInterface>
     */
    public function getHits(): array
    {
        if ($this->hits === null) {
            $query = $this->getQueryText();
            $this->hits = ($query === '' || !$this->isEnabled())
                ? []
                : $this->contentSearch->search($query, Config::MAX_LISTED, $this->storeId(), $this->showSnippet());
        }

        return $this->hits;
    }

    /**
     * Whether the block instance at $position renders, given whether the search found products.
     * Without products the block always stands above the "no results" message, whatever the setting.
     */
    public function rendersAt(string $position, bool $hasProducts): bool
    {
        $configured = $this->config->getPosition($this->storeId());
        $effective = $hasProducts ? $configured : Config::POSITION_ABOVE;

        return $effective === $position && $this->getHits() !== [];
    }

    public function getVisibleDesktop(): int
    {
        return min($this->config->getMaxResults($this->storeId()), count($this->getHits()));
    }

    public function getVisibleMobile(): int
    {
        return min(Config::MOBILE_VISIBLE, $this->getVisibleDesktop());
    }

    public function showSnippet(): bool
    {
        return $this->config->showSnippet($this->storeId());
    }

    /**
     * Cache tags of the block: every shown CMS page, and the list itself (cleaned on any index change).
     *
     * @return array<int, string>
     */
    public function getIdentities(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $tags = [Indexer::CACHE_TAG];
        foreach ($this->getHits() as $hit) {
            if ($hit->getType() === HitInterface::TYPE_CMS_PAGE) {
                $tags[] = Page::CACHE_TAG . '_' . $hit->getEntityId();
            }
        }

        return $tags;
    }

    private function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
