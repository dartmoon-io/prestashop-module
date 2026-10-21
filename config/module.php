<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

return [
    'name' => 'examplemodule',
    'display_name' => 'Example module',
    'description' => 'A PrestaShop module.',
    'version' => '1.0.0',
    'author' => 'Dartmoon',
    // Deliberately bounded to the verified shop release. Review before expanding.
    'compatibility' => ['min' => '9.2.0', 'max' => '9.2.0'],
    // Parents must precede children. Use TabManager OR native Module::$tabs
    // for a given tab to avoid registering the same tab twice.
    'tabs' => [
        [
            'class_name' => 'AdminExamplemoduleExample',
            'route_name' => 'examplemodule_admin_example',
            'parent_class_name' => 'AdminParentModulesSf',
            'name' => 'Example module',
            'icon' => 'code',
        ],
    ],
];
