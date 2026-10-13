<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Source;

use Magelan\SearchCms\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Position implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::POSITION_ABOVE, 'label' => __('Above the products')],
            ['value' => Config::POSITION_BELOW, 'label' => __('Below the products')],
        ];
    }
}
