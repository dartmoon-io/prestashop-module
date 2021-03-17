<?php

return [
    // The name of the module. This name will be used to create the zip and must be the same as the main php file
    'name' => 'MY_MODULE_NAME',

    // The common scoper config
    'scoper' => [
        // The prefix configuration. If a non null value will be used, a random prefix will be generated.
        'prefix' => 'Dartmoon\\MY_MODULE_NAME\\Vendor',

        // PHP-Scoper's goal is to make sure that all code for a project lies in a distinct PHP namespace. However, you
        // may want to share a common API between the bundled code of your PHAR and the consumer code. For example if
        // you have a PHPUnit PHAR with isolated code, you still want the PHAR to be able to understand the
        // PHPUnit\Framework\TestCase class.
        //
        // A way to achieve this is by specifying a list of classes to not prefix with the following configuration key. Note
        // that this does not work with functions or constants neither with classes belonging to the global namespace.
        //
        // Fore more see https://github.com/humbug/php-scoper#whitelist
        'whitelist' => [
            'PrestaShop\PrestaShop\Adapter\Entity\*',
        ],
    ]
];