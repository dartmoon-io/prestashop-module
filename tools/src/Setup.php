<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ModuleTools;

/** Dependency-free, so setup works before the first Composer install. */
final class Setup
{
    public static function run(string $root, array $options): void
    {
        $composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $metadata = $composer['extra']['prestashop-module'];
        if ($metadata['name'] !== 'examplemodule' || !is_file($root . '/examplemodule.php')) {
            throw new \RuntimeException('This module is already configured. Edit config/module.php for subsequent changes.');
        }
        $interactive = !isset($options['no-interaction']) && defined('STDIN') && stream_isatty(STDIN);
        $ask = static function (string $key, string $prompt, string $default) use ($options, $interactive): string {
            if (isset($options[$key])) {
                return (string) $options[$key];
            }
            if (!$interactive) {
                return $default;
            }
            fwrite(STDOUT, "{$prompt} [{$default}]: ");
            $answer = trim((string) fgets(STDIN));

            return $answer === '' ? $default : $answer;
        };
        $suggestion = strtolower(preg_replace('/[^a-z0-9_]/i', '', basename($root)));
        if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $suggestion)) {
            $suggestion = 'mymodule';
        }
        $name = $ask('name', 'Technical module name', $suggestion);
        if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $name) || $name === 'examplemodule') {
            throw new \InvalidArgumentException('Use a lowercase module name, 3–64 characters, starting with a letter; examplemodule is reserved for the skeleton.');
        }
        $namespace = $ask('namespace', 'PHP namespace', 'Dartmoon\\' . ucfirst($name));
        if (count(explode('\\', $namespace)) < 2 || preg_match('/^Dartmoon\\\\(?:Assets|Config|Console|Controllers|Downloaders|Filters|Helpers|Hooks|Importers|Log|Renderers|TabManager|Utils|Support)(?:\\\\|$)/i', $namespace)) {
            throw new \InvalidArgumentException('Use a vendor and module namespace, outside the toolkit component namespaces.');
        }
        $reserved = array_fill_keys(explode(' ', 'abstract and array as bool break callable case catch class clone const continue declare default die do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum eval exit extends false final finally float fn for foreach from function global goto if implements include include_once instanceof insteadof int interface isset iterable list match mixed namespace never new null object or parent print private protected public readonly require require_once resource return self static string switch throw trait true try unset use var void while xor yield'), true);
        foreach (array_merge(explode('\\', $namespace), [$name]) as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $part) || isset($reserved[strtolower($part)])) {
                throw new \InvalidArgumentException('Module name and namespace must use non-reserved PHP identifiers.');
            }
        }
        if (preg_match('/^(?:PrestaShop|PrestaShopBundle|Symfony|Doctrine|Twig|Psr)(?:\\\\|$)/i', $namespace)) {
            throw new \InvalidArgumentException('Use your own vendor namespace, outside PrestaShop host namespaces.');
        }
        $display = $ask('display-name', 'Display name', ucwords(str_replace('_', ' ', $name)));
        $author = $ask('author', 'Author', 'Dartmoon');
        $version = $ask('version', 'Version', '1.0.0');
        $description = $ask('description', 'Description', 'A PrestaShop module.');
        if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D', $version)) {
            throw new \InvalidArgumentException('Use a semantic version such as 1.0.0.');
        }
        if ($author === '' || $display === '') {
            throw new \InvalidArgumentException('Display name and author must not be empty.');
        }
        if (isset($options['toolkit-path'])) {
            $path = $options['toolkit-path'];
            $absolute = str_starts_with($path, '/') ? $path : $root . '/' . $path;
            $package = is_file($absolute . '/composer.json') ? json_decode(file_get_contents($absolute . '/composer.json'), true, 512, JSON_THROW_ON_ERROR) : [];
            if (($package['name'] ?? '') !== 'dartmoon/prestashop-module-toolkit') {
                throw new \InvalidArgumentException('--toolkit-path must point to the toolkit repository.');
            }
            $composer['repositories'] = [['type' => 'path', 'url' => $path, 'options' => ['symlink' => false, 'versions' => ['dartmoon/prestashop-module-toolkit' => '1.0.0']]]];
        }
        $composer['name'] = 'module/' . $name;
        $composer['description'] = $description;
        $composer['authors'] = [['name' => $author]];
        $composer['autoload']['psr-4'] = [$namespace . '\\' => 'app/'];
        $composer['extra']['prestashop-module'] = array_replace($metadata, ['name' => $name, 'namespace' => $namespace]);
        $config = require $root . '/config/module.php';
        $config = array_replace($config, ['name' => $name, 'display_name' => $display, 'author' => $author, 'version' => $version, 'description' => $description]);
        $adminController = 'Admin' . ucfirst($name) . 'Example';
        if (strlen($adminController) > 64) {
            $adminController = 'Admin' . ucfirst(substr($name, 0, 44)) . 'Example' . substr(hash('sha256', $name), 0, 8);
        }
        foreach ($config['tabs'] as &$tab) {
            if (($tab['class_name'] ?? '') === 'AdminExamplemoduleExample') {
                $tab['class_name'] = $adminController;
                $tab['route_name'] = $name . '_admin_example';
                $tab['name'] = $display;
            }
        }
        unset($tab);

        // Prepare every replacement before touching any file. No global text replacement
        // of user input into PHP strings or JSON: metadata is encoded in its target format.
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = str_replace(['Dartmoon\\ExampleModule', 'examplemodule:'], [$namespace, $name . ':'], file_get_contents($file->getPathname()));
            }
        }
        $main = str_replace(['Dartmoon\\ExampleModule', 'class Examplemodule '], [$namespace, 'class ' . ucfirst($name) . ' '], file_get_contents($root . '/examplemodule.php'));
        $files[$root . '/' . $name . '.php'] = $main;
        $files[$root . '/config/module.php'] = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        $files[$root . '/composer.json'] = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        // Symfony DI definitions are generated as PHP so namespace values stay literal.
        $files[$root . '/config/services.php'] = strtr(file_get_contents($root . '/config/services.php'), [
            'Dartmoon\\\\ExampleModule' => str_replace('\\', '\\\\', $namespace),
            'Dartmoon\\ExampleModule' => $namespace,
            'dartmoon.examplemodule.config' => $name . '.config',
        ]);
        $files[$root . '/config/routes.yml'] = strtr(file_get_contents($root . '/config/routes.yml'), [
            'examplemodule_admin_example' => $name . '_admin_example',
            '/examplemodule/example' => '/' . $name . '/example',
            'Dartmoon\\ExampleModule' => $namespace,
            'AdminExamplemoduleExample' => $adminController,
        ]);
        $files[$root . '/controllers/front/example.php'] = str_replace(
            'ExamplemoduleExampleModuleFrontController',
            ucfirst($name) . 'ExampleModuleFrontController',
            file_get_contents($root . '/controllers/front/example.php')
        );
        $files[$root . '/views/templates/front/example.tpl'] = str_replace(
            "mod='examplemodule'",
            "mod='" . $name . "'",
            file_get_contents($root . '/views/templates/front/example.tpl')
        );
        foreach ($files as $path => $content) {
            if (file_put_contents($path, $content) === false) {
                throw new \RuntimeException('Unable to write ' . $path);
            }
        }
        unlink($root . '/examplemodule.php');
        fwrite(STDOUT, "Configured {$name}. Run composer install, then composer tools:install.\n");
    }
}
