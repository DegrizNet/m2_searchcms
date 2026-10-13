<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Block\Adminhtml\System\Config;

use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * "Rebuild the page search list" button (with confirmation; result shown above the button).
 */
class RebuildButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Magelan_SearchCms::system/config/rebuild.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Indexer $indexer,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function isBuilt(): bool
    {
        return $this->indexer->getStatus()['rows'] > 0;
    }

    public function getWidgetConfig(): string
    {
        return (string) $this->json->serialize([
            'Magelan_SearchCms/js/rebuild' => [
                'url' => $this->getUrl('magelan_searchcms/index/rebuild'),
                'confirmTitle' => (string) __('Rebuild the page search list'),
                'confirmText' => (string) __('The current list is replaced with a new one built from all active CMS pages. The search results page keeps working the whole time.'),
                'confirmButton' => (string) __('Build'),
                'cancelButton' => (string) __('Cancel'),
                'busyLabel' => (string) __('Building ...'),
                'retryLabel' => (string) __('Try again'),
            ],
        ]);
    }
}
