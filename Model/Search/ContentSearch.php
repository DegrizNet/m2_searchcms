<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Search;

use Magelan\SearchCms\Api\ContentSearchInterface;
use Magelan\SearchCms\Api\Data\HitInterface;
use Magelan\SearchCms\Model\Config;
use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Searches the page list.
 *
 * 1. Every term must occur (in the title, main heading, meta data or text). 2. Only when no page has all
 * of them: pages with enough of the terms (see Ranking::requiredInFallback). Results are ordered by
 * Ranking, then by title.
 */
class ContentSearch implements ContentSearchInterface
{
    /** Candidates read from the database before ranking. */
    private const CANDIDATES = 300;

    /**
     * @var array<string, array<int, HitInterface>>
     */
    private array $cache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly QueryParser $parser,
        private readonly Ranking $ranking,
        private readonly Snippet $snippet,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url,
        private readonly DateTime $dateTime,
        private readonly ExclusionMatcher $exclusionMatcher
    ) {
    }

    /**
     * @inheritdoc
     */
    public function search(string $query, int $limit = 5, ?int $storeId = null, bool $withSnippet = true): array
    {
        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
        $limit = max(1, min($limit, Config::MAX_LISTED));
        $key = implode('|', [$storeId, $limit, (int) $withSnippet, $query]);
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->run($query, $limit, $storeId, $withSnippet);
        }

        return $this->cache[$key];
    }

    /**
     * @return array<int, HitInterface>
     */
    private function run(string $query, int $limit, int $storeId, bool $withSnippet): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }
        $terms = $this->parser->parse($query);
        if (!$terms) {
            return [];
        }
        $phrase = $this->parser->phrase($query);

        $rows = $this->fetch($terms, $storeId, true);
        $required = count($terms);
        if (!$rows && count($terms) > 1) {
            $rows = $this->fetch($terms, $storeId, false);
            $required = $this->ranking->requiredInFallback(count($terms));
        }
        if (!$rows) {
            return [];
        }

        $excluded = $this->config->getExcluded($storeId);
        $scored = [];
        foreach ($rows as $row) {
            if ($row['entity_type'] === HitInterface::TYPE_CMS_PAGE
                && $this->exclusionMatcher->isExcluded((string) $row['identifier'], $excluded)
            ) {
                continue;
            }
            $result = $this->ranking->score($row, $terms, $phrase);
            if ($result['matched'] < $required) {
                continue;
            }
            $row['_score'] = $result['score'];
            $scored[] = $row;
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['_score'] <=> $a['_score']
                ?: strcmp((string) $a['title_f'], (string) $b['title_f'])
                ?: (int) $a['entity_id'] <=> (int) $b['entity_id'];
        });

        $hits = [];
        foreach (array_slice($scored, 0, $limit) as $row) {
            $hits[] = new Hit(
                (string) $row['entity_type'],
                (int) $row['entity_id'],
                (string) $row['identifier'],
                (string) $row['title'],
                $this->buildUrl((string) $row['entity_type'], (string) $row['identifier'], $storeId),
                $withSnippet ? $this->snippet->build((string) $row['body'], $terms) : '',
                (int) $row['_score']
            );
        }

        return $hits;
    }

    /**
     * @param array<int, string> $terms
     * @return array<int, array<string, mixed>>
     */
    private function fetch(array $terms, int $storeId, bool $all): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(Indexer::TABLE);

        $select = $connection->select()
            ->from(['i' => $table], [
                'entity_type', 'entity_id', 'store_id', 'identifier', 'title', 'title_f', 'heading_f', 'meta_f',
                'body', 'body_f',
            ])
            ->where('i.store_id IN (?)', [0, $storeId])
            ->limit(self::CANDIDATES);

        // A page assigned to "All Store Views" is replaced by an active page with the same identifier
        // assigned to this store view (that is the page Magento shows at the URL).
        $select->where(
            'i.entity_type <> ? OR i.store_id <> 0 OR NOT EXISTS (' . $connection->select()
                ->from(['s' => $table], [new \Zend_Db_Expr('1')])
                ->where('s.entity_type = i.entity_type')
                ->where('s.identifier = i.identifier')
                ->where('s.store_id = ?', $storeId) . ')',
            HitInterface::TYPE_CMS_PAGE
        );

        $types = [HitInterface::TYPE_CMS_PAGE];
        if ($this->config->includeBlog($storeId)) {
            $types[] = HitInterface::TYPE_BLOG_POST;
            $select->where('i.available_from IS NULL OR i.available_from <= ?', $this->dateTime->gmtDate());
        }
        $select->where('i.entity_type IN (?)', $types);

        $minLength = $this->config->getMinLength($storeId);
        if ($minLength > 0) {
            $select->where('i.entity_type <> ? OR i.body_length >= ' . $minLength, HitInterface::TYPE_CMS_PAGE);
        }

        $conditions = [];
        foreach ($terms as $term) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $parts = [];
            foreach (['title_f', 'heading_f', 'meta_f', 'body_f'] as $column) {
                $parts[] = $connection->quoteInto('i.' . $column . ' LIKE ?', $like);
            }
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }
        $select->where(implode($all ? ' AND ' : ' OR ', $conditions));

        return $connection->fetchAll($select);
    }

    private function buildUrl(string $type, string $identifier, int $storeId): string
    {
        $path = $type === HitInterface::TYPE_BLOG_POST ? $this->blogRoutePrefix() . '/' . $identifier : $identifier;
        $store = $this->storeManager->getStore();
        if ((int) $store->getId() === $storeId) {
            return $this->url->getUrl('', ['_direct' => $path]);
        }

        // Another store view than the current one: a store bound URL builder (store code in the path).
        return $this->storeManager->getStore($storeId)->getUrl('', ['_direct' => $path, '_nosid' => true]);
    }

    /**
     * Magelan Blog's URL prefix ("blog"), read from the blog's router when available.
     */
    private function blogRoutePrefix(): string
    {
        $router = 'Magelan\\Blog\\Controller\\Router';
        if (class_exists($router) && defined($router . '::ROUTE_PREFIX')) {
            return (string) constant($router . '::ROUTE_PREFIX');
        }

        return 'blog';
    }
}
