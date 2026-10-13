<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Setup\Patch\Data;

use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds the page search list once on installation or upgrade (decided 2026-10-13: no manual step).
 * Afterwards the observers keep it fresh. A failure does not stop setup:upgrade; the settings page then
 * shows "Not built" with the rebuild button.
 */
class BuildIndex implements DataPatchInterface
{
    public function __construct(
        private readonly Indexer $indexer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        try {
            $this->indexer->rebuildAll();
        } catch (\Throwable $e) {
            $this->logger->error('Magelan_SearchCms: building the page search list during setup failed: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public static function getDependencies(): array
    {
        return [KeepPreviousEnabledState::class];
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
