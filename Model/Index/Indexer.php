<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Index;

use Magelan\SearchCms\Api\Data\HitInterface;
use Magelan\SearchCms\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\FlagManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Writes the search list: full rebuild, and one page or post at a time from the save/delete observers.
 *
 * After every change the cache tag "magelan_searchcms" is cleaned, so cached search result pages
 * (full page cache, Varnish) pick up new or edited pages.
 */
class Indexer
{
    public const TABLE = 'magelan_searchcms_index';
    public const FLAG = 'magelan_searchcms_index';
    public const CACHE_TAG = 'magelan_searchcms';

    private const BLOG_TRANSLATION_TABLE = 'magelan_blog_post_translation';
    private const BLOG_POST_TABLE = 'magelan_blog_post';
    private const CHUNK = 200;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly RowBuilder $rowBuilder,
        private readonly Config $config,
        private readonly FlagManager $flagManager,
        private readonly DateTime $dateTime,
        private readonly EventManager $eventManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Rebuilds the whole list. The old rows stay readable until the new ones are committed.
     *
     * @param callable|null $progress fn(int $done, int $total): void
     * @return array{built_at: string, pages: int, posts: int, empty: int, errors: array<string, string>}
     */
    public function rebuildAll(?callable $progress = null): array
    {
        $connection = $this->connection();
        $rows = [];
        $errors = [];
        $pageIds = [];
        $empty = 0;

        $pages = $this->loadCmsPages(null);
        $stores = $this->loadCmsPageStores(array_keys($pages));
        $total = count($pages);
        $done = 0;
        foreach ($pages as $pageId => $page) {
            try {
                $pageRows = $this->rowBuilder->fromCmsPage($page, $stores[$pageId] ?? []);
                foreach ($pageRows as $row) {
                    $rows[] = $row;
                }
                if ($pageRows) {
                    $pageIds[$pageId] = true;
                    $empty += $pageRows[0]['body_length'] === 0 ? 1 : 0;
                }
            } catch (\Throwable $e) {
                $errors[(string) $page['identifier']] = $e->getMessage();
                $this->logger->error(sprintf(
                    'Magelan_SearchCms: page %d "%s" could not be indexed: %s',
                    $pageId,
                    (string) $page['identifier'],
                    $e->getMessage()
                ));
            }
            $done++;
            if ($progress) {
                $progress($done, $total);
            }
        }

        $postIds = [];
        foreach ($this->loadBlogPosts(null) as $post) {
            try {
                $row = $this->rowBuilder->fromBlogPost($post);
                if ($row) {
                    $rows[] = $row;
                    $postIds[(int) $post['post_id']] = true;
                }
            } catch (\Throwable $e) {
                $errors['blog:' . (string) ($post['url_key'] ?? '')] = $e->getMessage();
                $this->logger->error('Magelan_SearchCms: blog post could not be indexed: ' . $e->getMessage());
            }
        }

        $table = $this->resource->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $connection->delete($table);
            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                $connection->insertMultiple($table, $this->normalise($chunk));
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $status = [
            'built_at' => $this->dateTime->gmtDate(),
            'pages' => count($pageIds),
            'posts' => count($postIds),
            'empty' => $empty,
            'errors' => $errors,
        ];
        $this->flagManager->saveFlag(self::FLAG, $status);
        $this->cleanCache();

        return $status;
    }

    /**
     * Re-reads one CMS page from the database and replaces its rows.
     *
     * @param array<string, mixed>|null $page page data as just saved (store_id/stores taken from it when
     *        present, because the store links may be written after the save event)
     */
    public function reindexCmsPage(int $pageId, ?array $page = null): void
    {
        if ($pageId <= 0) {
            return;
        }
        $fromDb = $this->loadCmsPages([$pageId]);
        $data = $fromDb[$pageId] ?? null;
        if ($data !== null && $page !== null) {
            foreach (['identifier', 'title', 'content_heading', 'content', 'meta_title', 'meta_keywords', 'meta_description', 'is_active'] as $key) {
                if (array_key_exists($key, $page)) {
                    $data[$key] = $page[$key];
                }
            }
        }
        $stores = $this->storesFromPageData($page) ?? ($this->loadCmsPageStores([$pageId])[$pageId] ?? []);

        $rows = $data !== null ? $this->rowBuilder->fromCmsPage($data, $stores) : [];
        $this->replace(HitInterface::TYPE_CMS_PAGE, [$pageId], $rows);
    }

    public function removeCmsPage(int $pageId): void
    {
        $this->replace(HitInterface::TYPE_CMS_PAGE, [$pageId], []);
    }

    /**
     * Re-reads all translations of one blog post (all store views) and replaces its rows.
     */
    public function reindexBlogPost(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }
        $rows = [];
        foreach ($this->loadBlogPosts($postId) as $post) {
            $row = $this->rowBuilder->fromBlogPost($post);
            if ($row) {
                $rows[] = $row;
            }
        }
        $this->replace(HitInterface::TYPE_BLOG_POST, [$postId], $rows);
    }

    /**
     * @return array{built_at: ?string, pages: int, posts: int, empty: int, errors: array<string, string>, rows: int}
     */
    public function getStatus(): array
    {
        $flag = $this->flagManager->getFlagData(self::FLAG);
        $flag = is_array($flag) ? $flag : [];
        $connection = $this->connection();
        $table = $this->resource->getTableName(self::TABLE);
        $rows = 0;
        $pages = 0;
        $empty = 0;
        if ($connection->isTableExists($table)) {
            $rows = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)']));
            $pages = (int) $connection->fetchOne(
                $connection->select()->from($table, ['COUNT(DISTINCT entity_id)'])
                    ->where('entity_type = ?', HitInterface::TYPE_CMS_PAGE)
            );
            $empty = (int) $connection->fetchOne(
                $connection->select()->from($table, ['COUNT(DISTINCT entity_id)'])
                    ->where('entity_type = ?', HitInterface::TYPE_CMS_PAGE)
                    ->where('body_length = 0')
            );
        }

        return [
            'built_at' => isset($flag['built_at']) ? (string) $flag['built_at'] : null,
            'pages' => $pages,
            'posts' => (int) ($flag['posts'] ?? 0),
            'empty' => $empty,
            'errors' => is_array($flag['errors'] ?? null) ? $flag['errors'] : [],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<int, int> $entityIds
     * @param array<int, array<string, mixed>> $rows
     */
    private function replace(string $type, array $entityIds, array $rows): void
    {
        $connection = $this->connection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $connection->delete($table, ['entity_type = ?' => $type, 'entity_id IN (?)' => $entityIds]);
            if ($rows) {
                $connection->insertMultiple($table, $this->normalise($rows));
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->cleanCache();
    }

    /**
     * Same column order in every row (insertMultiple requires it).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalise(array $rows): array
    {
        $columns = ['entity_type', 'entity_id', 'store_id', 'identifier', 'title', 'title_f', 'heading_f', 'meta_f', 'body', 'body_f', 'body_length', 'available_from'];
        $out = [];
        foreach ($rows as $row) {
            $clean = [];
            foreach ($columns as $column) {
                $clean[$column] = $row[$column] ?? null;
            }
            $out[] = $clean;
        }

        return $out;
    }

    /**
     * @param array<int, int>|null $pageIds null = all pages
     * @return array<int, array<string, mixed>> by page_id
     */
    private function loadCmsPages(?array $pageIds): array
    {
        $connection = $this->connection();
        $select = $connection->select()->from(
            $this->resource->getTableName('cms_page'),
            ['page_id', 'identifier', 'title', 'content_heading', 'content', 'meta_title', 'meta_keywords', 'meta_description', 'is_active']
        );
        if ($pageIds !== null) {
            $select->where('page_id IN (?)', $pageIds);
        }
        $pages = [];
        foreach ($connection->fetchAll($select) as $row) {
            $pages[(int) $row['page_id']] = $row;
        }

        return $pages;
    }

    /**
     * @param array<int, int> $pageIds
     * @return array<int, array<int, int>> page_id => store ids
     */
    private function loadCmsPageStores(array $pageIds): array
    {
        if (!$pageIds) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->resource->getTableName('cms_page_store'), ['page_id', 'store_id'])
            ->where('page_id IN (?)', $pageIds);
        $stores = [];
        foreach ($connection->fetchAll($select) as $row) {
            $stores[(int) $row['page_id']][] = (int) $row['store_id'];
        }

        return $stores;
    }

    /**
     * @param array<string, mixed>|null $page
     * @return array<int, int>|null
     */
    private function storesFromPageData(?array $page): ?array
    {
        if ($page === null) {
            return null;
        }
        foreach (['store_id', 'stores'] as $key) {
            if (!array_key_exists($key, $page) || $page[$key] === null || $page[$key] === '') {
                continue;
            }
            $value = is_array($page[$key]) ? $page[$key] : explode(',', (string) $page[$key]);
            $value = array_values(array_filter(array_map('trim', array_map('strval', $value)), 'strlen'));
            if ($value) {
                return array_map('intval', $value);
            }
        }

        return null;
    }

    /**
     * Published blog post translations (Magelan_Blog), when that module is installed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadBlogPosts(?int $postId): array
    {
        if (!$this->config->isBlogAvailable()) {
            return [];
        }
        $connection = $this->connection();
        $translations = $this->resource->getTableName(self::BLOG_TRANSLATION_TABLE);
        $posts = $this->resource->getTableName(self::BLOG_POST_TABLE);
        if (!$connection->isTableExists($translations) || !$connection->isTableExists($posts)) {
            return [];
        }
        $select = $connection->select()
            ->from(['t' => $translations], ['post_id', 'store_id', 'title', 'url_key', 'status', 'meta_title', 'meta_description', 'content_text'])
            ->joinInner(['p' => $posts], 'p.post_id = t.post_id', ['publish_at'])
            ->where('t.status = 1');
        if ($postId !== null) {
            $select->where('t.post_id = ?', $postId);
        }

        return $connection->fetchAll($select);
    }

    private function cleanCache(): void
    {
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => new CacheTags([self::CACHE_TAG])]);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
