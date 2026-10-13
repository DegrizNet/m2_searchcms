<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Block\Adminhtml\System\Config;

use Magelan\SearchCms\Model\Index\Indexer;
use Magelan\SearchCms\Model\Index\StatusText;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * "Page search list > Status" and "Pages without text" (read only rows).
 */
class IndexStatus extends Field
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Indexer $indexer,
        private readonly StatusText $statusText,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $state = $this->statusText->describe($this->indexer->getStatus());
        $escaper = $this->_escaper;

        $html = '<tr id="row_' . $escaper->escapeHtmlAttr($element->getHtmlId()) . '">'
            . '<td class="label"><label>' . $escaper->escapeHtml(__('Status')) . '</label></td>'
            . '<td class="value"><strong data-role="mgl-searchcms-status">' . $escaper->escapeHtml($state['text']) . '</strong>'
            . '<p class="note"><span>' . $escaper->escapeHtml(__(
                'The list is updated automatically when a CMS page is saved or deleted. Rebuild it by hand only after pages were imported directly into the database.'
            )) . '</span></p></td><td class="scope-label"></td><td></td></tr>';

        if ($state['built']) {
            $html .= '<tr id="row_' . $escaper->escapeHtmlAttr($element->getHtmlId()) . '_empty">'
                . '<td class="label"><label>' . $escaper->escapeHtml(__('Pages without text')) . '</label></td>'
                . '<td class="value"><span data-role="mgl-searchcms-empty">' . $escaper->escapeHtml($state['empty']) . '</span>'
                . '<p class="note"><span>' . $escaper->escapeHtml(__(
                    'Their content is in blocks or widgets, which the search does not see. They can still be found by their title.'
                )) . '</span></p></td><td class="scope-label"></td><td></td></tr>';
        }
        if ($state['errors'] !== '') {
            $html .= '<tr><td class="label"></td><td class="value"><div class="message message-error">'
                . $escaper->escapeHtml(__('Pages that could not be indexed: %1 (see var/log/magelan_searchcms.log)', $state['errors']))
                . '</div></td><td class="scope-label"></td><td></td></tr>';
        }

        return $html;
    }
}
