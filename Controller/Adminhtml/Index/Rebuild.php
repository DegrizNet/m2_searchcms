<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Controller\Adminhtml\Index;

use Magelan\SearchCms\Model\Index\Indexer;
use Magelan\SearchCms\Model\Index\StatusText;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * "Rebuild the page search list" button in Stores > Configuration > Magelan > Search CMS Pages (POST, form key).
 */
class Rebuild extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magelan_SearchCms::config_system';

    public function __construct(
        Context $context,
        private readonly Indexer $indexer,
        private readonly JsonFactory $jsonFactory,
        private readonly StatusText $statusText,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $status = $this->indexer->rebuildAll();
            $failed = array_keys($status['errors']);
            if ($failed) {
                $message = (string) __(
                    'Page "%1" could not be indexed. The other %2 pages are built; the reason is in var/log/magelan_searchcms.log.',
                    implode('", "', $failed),
                    $status['pages']
                );
            } else {
                $message = (string) __('Built %1 pages.', $status['pages']);
            }

            return $result->setData([
                'success' => !$failed,
                'message' => $message,
                'status' => $this->statusText->describe($this->indexer->getStatus()),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Magelan_SearchCms: rebuild failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => (string) __('The page search list could not be built: %1', $e->getMessage()),
            ]);
        }
    }
}
