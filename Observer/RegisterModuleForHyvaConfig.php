<?php

declare(strict_types=1);

namespace Magelan\SearchCms\Observer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Adds this module to app/etc/hyva-themes.json, so a Hyvä theme's Tailwind build scans the
 * module's Hyvä templates (view/frontend/tailwind/module.css for Tailwind 4,
 * tailwind.config.js for Tailwind 3).
 *
 * The event "hyva_config_generate_before" is dispatched only by Hyva_Theme
 * (bin/magento hyva:config:generate, setup:upgrade, module:enable). Without Hyvä it never fires,
 * and this class uses nothing but the Magento framework, so the module needs no Hyvä package.
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    private const MODULE = 'Magelan_SearchCms';

    /**
     * @var ComponentRegistrarInterface
     */
    private $componentRegistrar;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param DirectoryList $directoryList
     */
    public function __construct(ComponentRegistrarInterface $componentRegistrar, DirectoryList $directoryList)
    {
        $this->componentRegistrar = $componentRegistrar;
        $this->directoryList = $directoryList;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $config = $observer->getData('config');
        $path = (string) $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE);
        $root = rtrim(str_replace('\\', '/', (string) $this->directoryList->getRoot()), '/') . '/';
        $path = str_replace('\\', '/', $path);

        // Hyvä wants the path relative to the Magento root; a module outside it cannot be built.
        if (!$config instanceof DataObject || $path === '' || strpos($path, $root) !== 0) {
            return;
        }

        $extensions = $config->hasData('extensions') ? (array) $config->getData('extensions') : [];
        $extensions[] = ['src' => substr($path, strlen($root))];
        $config->setData('extensions', $extensions);
    }
}
