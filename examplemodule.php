<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

use Dartmoon\Config\Config;
use Dartmoon\ExampleModule\Hooks\BackOfficeAssetsHook;
use Dartmoon\ExampleModule\Hooks\FrontOfficeAssetsHook;
use Dartmoon\Hooks\Traits\HasHookDispatcher;
use Dartmoon\TabManager\TabManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Examplemodule extends Module
{
    use HasHookDispatcher;

    protected $hooks = [BackOfficeAssetsHook::class, FrontOfficeAssetsHook::class];
    private Config $moduleConfig;

    public function __construct()
    {
        $this->moduleConfig = new Config(__DIR__ . '/config');
        $metadata = $this->moduleConfig->get('module');
        $this->name = $metadata['name'];
        $this->version = $metadata['version'];
        $this->author = $metadata['author'];
        $this->tab = 'others';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = $metadata['compatibility'];
        parent::__construct();

        $this->displayName = $metadata['display_name'];
        $this->description = $metadata['description'];
        $this->confirmUninstall = $this->trans('Uninstall this module?', [], 'Admin.Notifications.Warning');
        $this->initHookDispatcher();
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        try {
            $hooks = $this->getHookDispatcher()->getAvailableHooks();
            if (($hooks !== [] && !$this->registerHook($hooks))
                || !$this->createTabManager()->install($this->moduleConfig->get('module.tabs', []), $this)
                || !$this->runSql('install')) {
                $this->uninstall();

                return false;
            }
        } catch (Throwable $exception) {
            $this->uninstall();
            throw $exception;
        }

        return true;
    }

    public function uninstall()
    {
        if (!$this->createTabManager()->uninstallForModule($this) || !$this->runSql('uninstall')) {
            return false;
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        return $this->fetch('module:' . $this->name . '/views/templates/admin/configure.tpl');
    }

    /** Override during lifecycle operations; the Symfony container may not be booted. */
    protected function createTabManager(): TabManager
    {
        return new TabManager();
    }

    private function runSql(string $action): bool
    {
        $queries = require __DIR__ . '/database/' . $action . '.php';
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }
}
