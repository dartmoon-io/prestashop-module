<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ExampleModule\Controllers\Admin;

use Dartmoon\Assets\AssetManager;
use Dartmoon\Config\Config;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\HttpFoundation\Response;

class ExampleController extends PrestaShopAdminController
{
    public function __construct(private Config $config)
    {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function index(): Response
    {
        $name = $this->config->get('module.name');

        return $this->render('@Modules/' . $name . '/views/templates/admin/example.html.twig', [
            'assets' => AssetManager::forModule(\Module::getInstanceByName($name))->render(['_dev/scss/back.scss', '_dev/js/back.js']),
            'layoutTitle' => $this->config->get('module.display_name'),
            'module_name' => $this->config->get('module.display_name'),
            'front_url' => \Context::getContext()->link->getModuleLink($name, 'example'),
        ]);
    }
}
