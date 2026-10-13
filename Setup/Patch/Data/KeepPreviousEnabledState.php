<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Setup\Patch\Data;

use Magelan\SearchCms\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * 2.0 is enabled by default (etc/config.xml). 1.x had no default, so a 1.x shop that never saved the
 * "Enabled" field had the block switched off. On an upgrade from 1.x that state is kept by writing
 * "0" at default scope; a value the shop saved explicitly is never touched. New installations
 * (no 1.x version in setup_module) get the new default.
 */
class KeepPreviousEnabledState implements DataPatchInterface
{
    private const MODULE = 'Magelan_SearchCms';

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $setupModule = $this->moduleDataSetup->getTable('setup_module');
        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        $previousVersion = $connection->isTableExists($setupModule)
            ? $connection->fetchOne(
                $connection->select()->from($setupModule, ['schema_version'])->where('module = ?', self::MODULE)
            )
            : false;
        if ($previousVersion === false || $previousVersion === null || version_compare((string) $previousVersion, '2.0.0', '>=')) {
            return $this;
        }

        $saved = $connection->fetchOne(
            $connection->select()->from($configTable, ['config_id'])
                ->where('path = ?', Config::XML_ENABLED)
                ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
                ->where('scope_id = ?', 0)
        );
        if ($saved === false) {
            $connection->insert($configTable, [
                'scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                'scope_id' => 0,
                'path' => Config::XML_ENABLED,
                'value' => '0',
            ]);
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
