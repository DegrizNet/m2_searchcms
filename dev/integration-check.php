<?php
/**
 * Integration check on a real Magento installation (not shipped: /dev is excluded from the package).
 *
 *   php app/code/Magelan/SearchCms/dev/integration-check.php <store_id> [<other_store_id>]
 *
 * Creates temporary CMS pages through the page repository and checks: the save observer fills the search
 * list, folded search ("vracilo" finds "Vračilo"), ranking (title above text), AND then OR, excluded
 * identifiers, store view assignment, deactivation and deletion, snippet marks, full rebuild. The pages are
 * deleted afterwards. Test settings are written at store view scope and restored at the end.
 */

declare(strict_types=1);

use Magelan\SearchCms\Api\ContentSearchInterface;
use Magelan\SearchCms\Model\Index\Indexer;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Bootstrap;

require getcwd() . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$storeId = (int) ($argv[1] ?? 1);
$otherStoreId = (int) ($argv[2] ?? 0);

$fails = 0;
$check = static function (string $name, bool $ok, string $info = '') use (&$fails) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($info !== '' ? '  ' . $info : '') . PHP_EOL;
    $fails += $ok ? 0 : 1;
};

// Test settings at store view scope; the previous store view values are restored at the end.
$configWriter = $om->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
$reinit = static function () use ($om): void {
    $om->get(\Magento\Framework\App\Cache\TypeListInterface::class)->cleanType('config');
    $om->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
};
$testConfig = [
    'searchcms/general/enabled' => '1',
    'searchcms/content/excluded' => "no-route\nmglcheck-excluded*",
    'searchcms/content/min_length' => '0',
];
$dbConnection = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$configTable = $om->get(\Magento\Framework\App\ResourceConnection::class)->getTableName('core_config_data');
$previousConfig = [];
foreach (array_filter([$storeId, $otherStoreId]) as $sid) {
    foreach ($testConfig as $path => $value) {
        $previousConfig[$sid][$path] = $dbConnection->fetchOne(
            $dbConnection->select()->from($configTable, ['value'])
                ->where('scope = ?', 'stores')->where('scope_id = ?', $sid)->where('path = ?', $path)
        );
        $configWriter->save($path, $value, 'stores', $sid);
    }
}
$reinit();

$repo = $om->get(PageRepositoryInterface::class);
$factory = $om->get(PageFactory::class);
$indexer = $om->get(Indexer::class);
$resource = $om->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$table = $resource->getTableName(Indexer::TABLE);
$suffix = substr(md5((string) microtime(true)), 0, 6);
$created = [];

$make = static function (string $identifier, string $title, string $content, array $stores, int $active = 1) use ($factory, $repo, &$created, $suffix) {
    $page = $factory->create();
    $page->setData([
        'identifier' => $identifier . '-' . $suffix,
        'title' => $title,
        'content' => $content,
        'is_active' => $active,
        'page_layout' => '1column',
        'store_id' => $stores,
    ]);
    $page = $repo->save($page);
    $created[] = (int) $page->getId();

    return $page;
};

$fresh = static function () use ($om): ContentSearchInterface {
    // a new instance: ContentSearch keeps an in-request cache
    return $om->create(ContentSearchInterface::class);
};

$ids = static fn (array $hits): array => array_map(static fn ($h) => $h->getEntityId(), $hits);

try {
    $word = 'mglx' . $suffix;
    $a = $make('mglcheck-vracilo', 'Vračilo in ' . $word, '<p>Rok za vračilo je 14 dni. ' . $word . ' tekst.</p>', [$storeId]);
    $b = $make('mglcheck-onas', 'O nas', '<p>Več o tem: ' . $word . ' ' . $word . '. Brez besede v naslovu.</p>{{widget type="x"}}', [0]);
    $c = $make('mglcheck-excluded', 'Izključena ' . $word, '<p>' . $word . '</p>', [$storeId]);
    $d = $make('mglcheck-inactive', 'Neaktivna ' . $word, '<p>' . $word . '</p>', [$storeId], 0);

    $rows = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)'])->where('entity_id IN (?)', [$a->getId(), $b->getId()])->where('entity_type = ?', 'cms_page'));
    $check('save observer writes rows', $rows === 2, "rows=$rows");

    $hits = $fresh()->search($word, 10, $storeId);
    $check('finds active pages of the store (and all store views)', $ids($hits) === [(int) $a->getId(), (int) $b->getId()], json_encode($ids($hits)));
    $check('title above text', ($hits[0] ?? null) && $hits[0]->getEntityId() === (int) $a->getId());
    $check('excluded identifier with * is not found', !in_array((int) $c->getId(), $ids($hits), true));
    $check('inactive page is not found', !in_array((int) $d->getId(), $ids($hits), true));
    $check('snippet marks the term', ($hits[0] ?? null) && str_contains($hits[0]->getSnippetHtml(), '<mark>' . $word . '</mark>'), $hits[0]?->getSnippetHtml() ?? '');
    $check('url ends with identifier', ($hits[0] ?? null) && str_ends_with($hits[0]->getUrl(), 'mglcheck-vracilo-' . $suffix), $hits[0]?->getUrl() ?? '');

    $hits = $fresh()->search('VRACILO ' . $word, 10, $storeId);
    $check('folded search: VRACILO finds Vračilo (AND)', $ids($hits) === [(int) $a->getId()], json_encode($ids($hits)));

    $hits = $fresh()->search($word . ' neobstojecebesedilo', 10, $storeId);
    $check('two terms, one missing everywhere: nothing (no noise)', $hits === [], json_encode($ids($hits)));

    $hits = $fresh()->search($word . ' vracilo zzzneobstoja', 10, $storeId);
    $check('three terms, fallback needs 2 of 3', $ids($hits) === [(int) $a->getId()], json_encode($ids($hits)));

    $hits = $fresh()->search('  ' . $word . '   ', 10, $storeId);
    $check('extra spaces do not list every page', count($hits) === 2, (string) count($hits));

    $hits = $fresh()->search('%', 10, $storeId);
    $check('"%" alone finds nothing', $hits === []);

    $hits = $fresh()->search('widget', 10, $storeId);
    $check('directive text is not searchable', !in_array((int) $b->getId(), $ids($hits), true));

    if ($otherStoreId > 0) {
        $hits = $fresh()->search($word, 10, $otherStoreId);
        $check('other store: only the all-store-views page', $ids($hits) === [(int) $b->getId()], json_encode($ids($hits)));
        // (Magento itself refuses a store specific page with the identifier of an all-store-views page:
        // "URL key for specified store already exists", so that case needs no test here.)
    }

    // deactivate
    $a->setIsActive(0);
    $repo->save($a);
    $hits = $fresh()->search($word, 10, $storeId);
    $check('deactivated page disappears after save', !in_array((int) $a->getId(), $ids($hits), true), json_encode($ids($hits)));

    // move to another store view
    if ($otherStoreId > 0) {
        $a->setIsActive(1);
        $a->setData('store_id', [$otherStoreId]);
        $repo->save($a);
        $hits = $fresh()->search('vracilo ' . $word, 10, $storeId);
        $check('page moved to another store view leaves this store', $hits === [], json_encode($ids($hits)));
        $hits = $fresh()->search('vracilo ' . $word, 10, $otherStoreId);
        $check('...and appears in the other one', $ids($hits) === [(int) $a->getId()], json_encode($ids($hits)));
    }

    // delete
    $repo->delete($b);
    $created = array_values(array_diff($created, [(int) $b->getId()]));
    $rows = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)'])->where('entity_id = ?', $b->getId())->where('entity_type = ?', 'cms_page'));
    $check('delete observer removes rows', $rows === 0);

    // full rebuild keeps the same rows
    $before = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)']));
    $status = $indexer->rebuildAll();
    $after = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)']));
    $check('full rebuild gives the same row count', $before === $after, "$before / $after");
    $check('rebuild status', $status['pages'] > 0 && $status['errors'] === [], json_encode($status));

    // cache identities
    $vm = $om->create(\Magelan\SearchCms\ViewModel\SearchResults::class);
    $check('view model identities include the list tag', in_array(Indexer::CACHE_TAG, $vm->getIdentities(), true) || !$vm->isEnabled());
} finally {
    foreach ($created as $id) {
        try {
            $repo->deleteById($id);
        } catch (\Throwable $e) {
            echo 'cleanup: ' . $e->getMessage() . PHP_EOL;
        }
    }
    foreach ($previousConfig as $sid => $paths) {
        foreach ($paths as $path => $value) {
            if ($value === false) {
                $configWriter->delete($path, 'stores', $sid);
            } else {
                $configWriter->save($path, $value, 'stores', $sid);
            }
        }
    }
    $reinit();
}

echo $fails ? "FAILED: $fails" . PHP_EOL : 'ALL PASSED' . PHP_EOL;
exit($fails ? 1 : 0);
