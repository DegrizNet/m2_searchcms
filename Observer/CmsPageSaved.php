<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Observer;

use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * cms_page_save_after: refreshes the page's rows in the search list (content, title, status, store views).
 * A failure is logged and never blocks saving the page.
 */
class CmsPageSaved implements ObserverInterface
{
    public function __construct(
        private readonly Indexer $indexer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $page = $observer->getEvent()->getData('object') ?? $observer->getEvent()->getData('data_object');
        if (!$page instanceof Page || !$page->getId()) {
            return;
        }
        try {
            $this->indexer->reindexCmsPage((int) $page->getId(), $page->getData());
        } catch (\Throwable $e) {
            $this->logger->error('Magelan_SearchCms: page ' . $page->getId() . ' not updated in the search list: ' . $e->getMessage());
        }
    }
}
