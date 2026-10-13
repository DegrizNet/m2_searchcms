<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Block\Adminhtml\System\Config;

use Magelan\SearchCms\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * "Search blog posts too": available only when Magelan Blog is installed; otherwise shown disabled with
 * the reason.
 */
class BlogField extends Field
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        if ($this->config->isBlogAvailable()) {
            $element->setComment(__('Blog posts are listed with the label "Blog".'));
        } else {
            $element->setDisabled(true);
            $element->setValue('0');
            $element->setComment(__('Not available: the Magelan Blog module is not installed.'));
        }

        return parent::_getElementHtml($element);
    }
}
