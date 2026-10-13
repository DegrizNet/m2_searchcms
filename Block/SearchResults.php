<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Block;

use Magelan\SearchCms\Model\Config;
use Magelan\SearchCms\ViewModel\SearchResults as ViewModel;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;

/**
 * "Pages matching your search" on the search results page. The layout places two instances, one above
 * and one below the product list (argument "position"); only the one matching the setting renders.
 * The class name is kept from 1.x for themes that reference it.
 */
class SearchResults extends Template implements IdentityInterface
{
    private const RESULT_BLOCK = 'search.result';

    public function getViewModel(): ViewModel
    {
        /** @var ViewModel $viewModel */
        $viewModel = $this->getData('view_model');

        return $viewModel;
    }

    public function getPosition(): string
    {
        return $this->getData('position') === Config::POSITION_BELOW ? Config::POSITION_BELOW : Config::POSITION_ABOVE;
    }

    /**
     * @deprecated 2.0.0 kept for themes that called it from a 1.x template
     */
    public function isModuleEnabled(): bool
    {
        return $this->getViewModel()->isEnabled();
    }

    public function shouldRender(): bool
    {
        return $this->getViewModel()->rendersAt($this->getPosition(), $this->hasProducts());
    }

    /**
     * Whether the catalog search found products (read from the core result block).
     */
    public function hasProducts(): bool
    {
        $result = $this->getLayout()->getBlock(self::RESULT_BLOCK);
        if (!$result || !method_exists($result, 'getResultCount')) {
            return true;
        }
        try {
            return (int) $result->getResultCount() > 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getIdentities()
    {
        return $this->getViewModel()->getIdentities();
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->getViewModel()->isEnabled() || !$this->shouldRender()) {
            return '';
        }

        return parent::_toHtml();
    }
}
