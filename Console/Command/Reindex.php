<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Console\Command;

use Magelan\SearchCms\Model\Index\Indexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento magelan:searchcms:reindex - rebuilds the search list of CMS pages (and blog posts).
 * Needed only after pages were imported directly into the database; saving a page updates it on its own.
 */
class Reindex extends Command
{
    /**
     * @param Indexer $indexer a proxy (etc/di.xml): the command list is built on every bin/magento call
     */
    public function __construct(private readonly Indexer $indexer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('magelan:searchcms:reindex')
            ->setDescription('Rebuild the search list of CMS pages (Magelan Search CMS Pages)');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->indexer->rebuildAll();
        $output->writeln(sprintf(
            '<info>Built %d pages%s.</info>',
            $status['pages'],
            $status['posts'] ? sprintf(' and %d blog posts', $status['posts']) : ''
        ));
        if ($status['empty']) {
            $output->writeln(sprintf(
                '%d pages have no text of their own (content in blocks or widgets, which search does not see).',
                $status['empty']
            ));
        }
        foreach ($status['errors'] as $identifier => $message) {
            $output->writeln(sprintf('<error>Page "%s" could not be indexed: %s</error>', $identifier, $message));
        }

        return $status['errors'] ? Command::FAILURE : Command::SUCCESS;
    }
}
