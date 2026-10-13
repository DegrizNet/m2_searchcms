<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Observer;

use Magelan\SearchCms\Model\Config;
use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * model_save_after / model_delete_after: refreshes a Magelan Blog post in the search list when one of its
 * translations or the post itself is saved or deleted.
 *
 * The blog is optional, so its classes are compared by name only (no import, no dependency): when
 * Magelan_Blog is not installed nothing here matches and the observer returns at once.
 */
class BlogPostChanged implements ObserverInterface
{
    private const BLOG_CLASSES = [
        'Magelan\\Blog\\Model\\Translation',
        'Magelan\\Blog\\Model\\Post',
    ];

    public function __construct(
        private readonly Indexer $indexer,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $object = $observer->getEvent()->getData('object');
        if (!is_object($object) || !$this->isBlogModel($object) || !$this->config->isBlogAvailable()) {
            return;
        }
        $postId = (int) $object->getData('post_id');
        if ($postId <= 0) {
            return;
        }
        try {
            $this->indexer->reindexBlogPost($postId);
        } catch (\Throwable $e) {
            $this->logger->error('Magelan_SearchCms: blog post ' . $postId . ' not updated in the search list: ' . $e->getMessage());
        }
    }

    private function isBlogModel(object $object): bool
    {
        foreach (self::BLOG_CLASSES as $class) {
            if (is_a($object, $class)) {
                return true;
            }
        }

        return false;
    }
}
