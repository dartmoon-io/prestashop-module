<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

class ExamplemoduleExampleModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        parent::initContent();
        $this->context->smarty->assign([
            'example_module_name' => $this->module->displayName,
        ]);
        $this->setTemplate('module:' . $this->module->name . '/views/templates/front/example.tpl');
    }
}
