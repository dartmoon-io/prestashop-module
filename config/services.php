<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

use Dartmoon\Config\Config;
use Dartmoon\ExampleModule\Controllers\Admin\ExampleController;
use Dartmoon\ExampleModule\Filters\Greeting;
use Dartmoon\Filters\FilterService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();
    $services->load('Dartmoon\\ExampleModule\\Commands\\', '../app/Commands/')
        ->exclude('../app/Commands/**/index.php');
    $services->set(Greeting::class);
    $services->set(FilterService::class)->args([[service(Greeting::class)]]);
    $services->set('dartmoon.examplemodule.config', Config::class)->args([__DIR__]);
    $services->set(ExampleController::class)
        ->arg('$config', service('dartmoon.examplemodule.config'))
        ->public()
        ->tag('controller.service_arguments');
};
