<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Helper;

use Magelan\SearchCms\Model\Config;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * @deprecated 2.0.0 use \Magelan\SearchCms\Model\Config; kept for code written against 1.x
 */
class Data extends AbstractHelper
{
    public const XML_PATH_ENABLED = Config::XML_ENABLED;

    public function __construct(Context $context, private readonly Config $config)
    {
        parent::__construct($context);
    }

    /**
     * @param int|string|null $store
     */
    public function isModuleEnabled($store = null): bool
    {
        return $this->config->isEnabled($store);
    }
}
