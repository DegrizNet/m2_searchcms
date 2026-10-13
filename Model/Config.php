<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\ScopeInterface;

/**
 * Settings: Stores > Configuration > Magelan > Search CMS Pages (section id "searchcms", kept from 1.x).
 */
class Config
{
    public const XML_ENABLED = 'searchcms/general/enabled';
    public const XML_POSITION = 'searchcms/general/position';
    public const XML_MAX_RESULTS = 'searchcms/general/max_results';
    public const XML_SHOW_SNIPPET = 'searchcms/general/show_snippet';
    public const XML_EXCLUDED = 'searchcms/content/excluded';
    public const XML_INCLUDE_BLOG = 'searchcms/content/include_blog';
    public const XML_MIN_LENGTH = 'searchcms/content/min_length';

    public const POSITION_ABOVE = 'above';
    public const POSITION_BELOW = 'below';

    /** Rows visible on phones before "Show all" (decided 2026-10-13: 3 on mobile, the setting on desktop). */
    public const MOBILE_VISIBLE = 3;

    /** Hard cap of results listed in the block, "Show all" included. */
    public const MAX_LISTED = 50;

    public const BLOG_MODULE = 'Magelan_Blog';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function isEnabled(int|string|null $store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $store);
    }

    public function getPosition(int|string|null $store = null): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_POSITION, ScopeInterface::SCOPE_STORE, $store);

        return $value === self::POSITION_BELOW ? self::POSITION_BELOW : self::POSITION_ABOVE;
    }

    public function getMaxResults(int|string|null $store = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_MAX_RESULTS, ScopeInterface::SCOPE_STORE, $store);

        return $value > 0 ? min($value, self::MAX_LISTED) : 5;
    }

    public function showSnippet(int|string|null $store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_SHOW_SNIPPET, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @return array<int, string> identifiers, a trailing "*" matches any ending
     */
    public function getExcluded(int|string|null $store = null): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_EXCLUDED, ScopeInterface::SCOPE_STORE, $store);

        return self::parseIdentifierList($value);
    }

    public function includeBlog(int|string|null $store = null): bool
    {
        return $this->isBlogAvailable()
            && $this->scopeConfig->isSetFlag(self::XML_INCLUDE_BLOG, ScopeInterface::SCOPE_STORE, $store);
    }

    public function getMinLength(int|string|null $store = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_MIN_LENGTH, ScopeInterface::SCOPE_STORE, $store));
    }

    public function isBlogAvailable(): bool
    {
        return $this->moduleManager->isEnabled(self::BLOG_MODULE);
    }

    /**
     * One identifier per line (commas and spaces between entries are tolerated).
     *
     * @return array<int, string>
     */
    public static function parseIdentifierList(string $value): array
    {
        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        $list = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $list[strtolower($part)] = $part;
            }
        }

        return array_values($list);
    }
}
