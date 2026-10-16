<?php

declare(strict_types=1);

namespace Dartmoon\ModuleTools;

final class Doctor
{
    public static function run(string $root, array $options): void
    {
        $composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $name = $composer['extra']['prestashop-module']['name'];
        foreach ([$name . '.php', 'vendor/autoload.php', 'composer.lock', 'config/module.php'] as $file) {
            if (!is_file($root . '/' . $file)) {
                throw new \RuntimeException('Missing ' . $file . '; configure the module and run composer install.');
            }
        }
        $metadata = require $root . '/config/module.php';
        if ($name === 'examplemodule' || $metadata['name'] !== $name) {
            throw new \RuntimeException('Module metadata is not configured or names disagree.');
        }
        if (isset($options['prestashop-root'])) {
            $shop = rtrim($options['prestashop-root'], '/');
            if (!is_file($shop . '/autoload.php') || !is_file($shop . '/classes/module/Module.php')) {
                throw new \RuntimeException('This is not a PrestaShop installation.');
            }
            $kernel = file_get_contents($shop . '/app/AppKernel.php');
            if (!preg_match('/MAJOR_VERSION\s*=\s*(\d+)/', $kernel, $major) || !preg_match('/MINOR_VERSION\s*=\s*(\d+)/', $kernel, $minor) || version_compare($major[1] . '.' . $minor[1], '9.2', '<')) {
                throw new \RuntimeException('PrestaShop 9.2 or later is required.');
            }
            echo 'PrestaShop ' . $major[1] . '.' . $minor[1] . " installation found.\n";
        }
        echo "Module metadata, runtime autoload and build tools are ready.\n";
    }
}
