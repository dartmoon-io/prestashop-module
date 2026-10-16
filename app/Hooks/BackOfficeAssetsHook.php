<?php

declare(strict_types=1);

namespace Dartmoon\ExampleModule\Hooks;

use Dartmoon\Assets\AssetManager;
use Dartmoon\Hooks\AbstractHookGroup;

class BackOfficeAssetsHook extends AbstractHookGroup
{
    protected $hooks = ['displayBackOfficeHeader'];

    public function displayBackOfficeHeader(array $params): string
    {
        if (\Tools::getValue('configure') !== $this->module->name) {
            return '';
        }

        return AssetManager::forModule($this->module)->render([
            '_dev/scss/back.scss',
            '_dev/js/back.js',
        ]);
    }
}
