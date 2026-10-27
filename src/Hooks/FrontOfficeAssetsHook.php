<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ExampleModule\Hooks;

use Dartmoon\Assets\AssetManager;
use Dartmoon\Hooks\AbstractHookGroup;

class FrontOfficeAssetsHook extends AbstractHookGroup
{
    protected $hooks = ['displayHeader'];

    public function displayHeader(array $params): string
    {
        if (!$this->context->controller instanceof \FrontController) {
            return '';
        }

        return AssetManager::forModule($this->module)->render([
            '_dev/scss/front.scss',
            '_dev/js/front.js',
        ]);
    }
}
