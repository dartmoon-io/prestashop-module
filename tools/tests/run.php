<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

$root = realpath(getenv('TEMPLATE_PATH') ?: dirname(__DIR__, 2));
if (!$root || !is_file($root . '/examplemodule.php')) {
    throw new RuntimeException('Scaffolding regression tests need an unconfigured boilerplate. Set TEMPLATE_PATH to that checkout.');
}
require $root . '/tools/vendor/autoload.php';
$filesystem = new Filesystem();
$temp = sys_get_temp_dir() . '/dm-module-tests-' . bin2hex(random_bytes(6));
$toolkit = realpath(getenv('TOOLKIT_PATH') ?: $root . '/../prestashop-module-toolkit');
if (!$toolkit) {
    throw new RuntimeException('Set TOOLKIT_PATH to the toolkit repository.');
}
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$run = static function (array $command, string $cwd, bool $success = true): Process {
    $process = new Process($command, $cwd);
    $process->setTimeout(300);
    $process->run();
    if ($process->isSuccessful() !== $success
        || ($success && preg_match('/PHP (?:Warning:|Fatal error:)/', $process->getOutput() . $process->getErrorOutput()))) {
        throw new RuntimeException($process->getCommandLine() . "\n" . $process->getOutput() . $process->getErrorOutput());
    }

    return $process;
};
$json = static fn (string $path): array => json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$writeJson = static fn (string $path, array $data) => file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

try {
    $policy = $temp . '/policy';
    $filesystem->mkdir([$policy . '/nested', $policy . '/.hidden', $policy . '/vendor/third-party']);
    foreach ([null, " \r\n"] as $invalidHeader) {
        if ($invalidHeader !== null) {
            file_put_contents($policy . '/copyright.txt', $invalidHeader);
        }
        foreach (['stamp', 'protect'] as $method) {
            try {
                Dartmoon\ModuleTools\ReleasePolicy::$method($policy);
                throw new LogicException('Expected an invalid copyright file to be rejected.');
            } catch (RuntimeException $exception) {
                $check(str_contains($exception->getMessage(), 'copyright.txt'), 'Copyright validation did not identify the file.');
            }
        }
    }
    $notice = "Copyright (c) 2026 Acme\nSPDX-License-Identifier: MIT";
    file_put_contents($policy . '/copyright.txt', str_replace("\n", "\r\n", $notice) . "\r\n");
    file_put_contents($policy . '/nested/source.php', '<?php return 42;');
    file_put_contents($policy . '/nested/index.php', '<?php $preserved = true;');
    file_put_contents($policy . '/nested/licensed.php', "<?php\n/** Copyright (c) Original author */\nreturn 1;");
    file_put_contents($policy . '/vendor/third-party/source.php', '<?php // Third-party copyright');
    file_put_contents($policy . '/composer.json', '{"php":"<?php literal"}');
    $headers = [
        'php' => "/**\n * " . str_replace("\n", "\n * ", $notice) . "\n */",
        'js' => "/**\n * " . str_replace("\n", "\n * ", $notice) . "\n */",
        'yml' => '# ' . str_replace("\n", "\n# ", $notice),
        'twig' => '{# ' . $notice . ' #}',
        'tpl' => '{* ' . $notice . ' *}',
    ];
    foreach (['js', 'yml', 'twig', 'tpl'] as $extension) {
        file_put_contents($policy . '/nested/source.' . $extension, 'source');
    }
    $run([PHP_BINARY, $root . '/tools/headers.php', $policy], $root);
    $once = file_get_contents($policy . '/nested/source.php');
    $check(str_starts_with($once, "<?php\n" . $headers['php']), 'PHP header did not use copyright.txt.');
    foreach ($headers as $extension => $header) {
        $check(str_contains(file_get_contents($policy . '/nested/source.' . $extension), $header), 'Custom header missing for ' . $extension);
    }
    Dartmoon\ModuleTools\ReleasePolicy::stamp($policy);
    $check($once === file_get_contents($policy . '/nested/source.php'), 'License stamping is not idempotent.');
    $check(file_get_contents($policy . '/nested/licensed.php') === "<?php\n/** Copyright (c) Original author */\nreturn 1;", 'An existing source notice was modified.');
    $check(file_get_contents($policy . '/composer.json') === '{"php":"<?php literal"}', 'JSON was modified by license stamping.');
    $check(file_get_contents($policy . '/vendor/third-party/source.php') === '<?php // Third-party copyright', 'Vendor notices were modified.');
    Dartmoon\ModuleTools\ReleasePolicy::protect($policy);
    $check(str_contains(file_get_contents($policy . '/index.php'), $headers['php']), 'Directory guard did not use copyright.txt.');
    $check(is_file($policy . '/.hidden/index.php'), 'Hidden release directories must be protected too.');
    $preserved = false;
    require $policy . '/nested/index.php';
    $check($preserved, 'Existing index behavior was lost.');
    require $policy . '/index.php';
    $check(true, 'An included directory guard interrupted execution.');
    $direct = $run([PHP_BINARY, '-r', '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($policy . '/index.php', true) . '; require $_SERVER["SCRIPT_FILENAME"]; echo http_response_code();'], $policy);
    $check($direct->getOutput() === '403', 'A direct guard request did not reject access.');

    // Force the second final rename to fail after the first output was published.
    file_put_contents($policy . '/previous-module', 'last good module');
    file_put_contents($policy . '/previous.zip', 'last good archive');
    file_put_contents($policy . '/pending-module', 'new module');
    $publish = new ReflectionMethod(Dartmoon\ModuleTools\Builder::class, 'publish');
    try {
        $publish->invoke(new Dartmoon\ModuleTools\Builder($root), [
            [$policy . '/pending-module', $policy . '/previous-module'],
            [$policy . '/missing.zip', $policy . '/previous.zip'],
        ]);
        throw new RuntimeException('Expected publication failure.');
    } catch (Symfony\Component\Filesystem\Exception\IOException $exception) {
        $check(file_get_contents($policy . '/previous-module') === 'last good module', 'Failed publication lost the previous module.');
        $check(file_get_contents($policy . '/previous.zip') === 'last good archive', 'Failed publication lost the previous archive.');
    }

    $filesystem->mkdir($temp . '/dependency/src');
    $writeJson($temp . '/dependency/composer.json', [
        'name' => 'test/build-fixture', 'version' => '1.0.0', 'type' => 'library',
        'autoload' => ['psr-4' => ['BuildFixture\\' => 'src/'], 'classmap' => ['src/globals.php'], 'files' => ['src/functions.php', 'src/globals.php', 'src/autoload.php']],
    ]);
    file_put_contents($temp . '/dependency/src/Widget.php', '<?php namespace BuildFixture; class Widget { public static function label(): string { return "isolated"; } }');
    file_put_contents($temp . '/dependency/src/functions.php', '<?php namespace BuildFixture; function answer(): int { return 42; }');
    file_put_contents($temp . '/dependency/src/globals.php', '<?php class GlobalWidget {} function globalAnswer(): int { return 7; } const GLOBAL_FIXTURE_VALUE = 11;');
    file_put_contents($temp . '/dependency/src/autoload.php', '<?php namespace BuildFixture; function autoloadAnswer(): int { return 3; }');
    file_put_contents($temp . '/dependency/src/asset.json', '{"keep":"resource"}');
    $modules = [];
    foreach (['One', 'Two'] as $suffix) {
        $directory = $temp . '/validation' . strtolower($suffix);
        $name = basename($directory);
        $namespace = 'Acme\\Validation' . $suffix;
        $filesystem->mirror($root, $directory, Finder::create()->in($root)->ignoreDotFiles(false)->exclude(['.git', 'vendor', 'tools/vendor', 'node_modules', 'dist']));
        // The tools' generated loader uses its original path; no development files
        // are shipped in the production module itself.
        $filesystem->symlink($root . '/tools/vendor', $directory . '/tools/vendor');
        $before = hash_file('sha256', $directory . '/composer.json');
        $run([PHP_BINARY, 'bin/module', 'setup', '--no-interaction', '--name=bad-name'], $directory, false);
        $check($before === hash_file('sha256', $directory . '/composer.json'), 'Invalid setup mutated the manifest.');
        $run([PHP_BINARY, 'bin/module', 'setup', '--no-interaction', '--name=' . $name, '--namespace=Dartmoon\\Assets'], $directory, false);
        $check($before === hash_file('sha256', $directory . '/composer.json'), 'Reserved asset namespace mutated the manifest.');
        $run([PHP_BINARY, 'bin/module', 'setup', '--no-interaction', '--name=' . $name, '--namespace=Dartmoon\\Helpers'], $directory, false);
        $check($before === hash_file('sha256', $directory . '/composer.json'), 'Reserved helper namespace mutated the manifest.');
        $run([PHP_BINARY, 'bin/module', 'setup', '--no-interaction', '--name=' . $name, '--namespace=Dartmoon\\Log'], $directory, false);
        $check($before === hash_file('sha256', $directory . '/composer.json'), 'Reserved log namespace mutated the manifest.');
        $run([PHP_BINARY, 'bin/module', 'setup', '--no-interaction', '--name=' . $name, '--namespace=' . $namespace, '--display-name=O\'Reilly "Demo"', '--author=Author\\Name', '--toolkit-path=' . $toolkit], $directory);
        $run([PHP_BINARY, 'bin/module', 'setup', '--no-interaction'], $directory, false);
        $copyright = 'Copyright (c) 2026 Validation ' . $suffix;
        file_put_contents($directory . '/copyright.txt', $copyright . "\n");
        $config = require $directory . '/config/module.php';
        $check($config['display_name'] === 'O\'Reilly "Demo"', 'Metadata quoting was not preserved.');
        $check(str_contains(file_get_contents($directory . '/config/services.php'), str_replace('\\', '\\\\', $namespace)), 'DI namespace was not configured.');
        $check(str_contains(file_get_contents($directory . '/app/Commands/ExampleCommand.php'), $name . ':hello'), 'Command name was not configured.');
        $check($config['tabs'][0]['class_name'] === 'Admin' . ucfirst($name) . 'Example', 'Example tab was not configured.');
        $check($config['tabs'][0]['route_name'] === $name . '_admin_example', 'Example route name was not configured.');
        $check(str_contains(file_get_contents($directory . '/config/routes.yml'), $namespace . '\\Controllers\\Admin\\ExampleController::index'), 'Controller route namespace was not configured.');
        $check(str_contains(file_get_contents($directory . '/controllers/front/example.php'), 'class ' . ucfirst($name) . 'ExampleModuleFrontController'), 'Front controller class was not configured.');
        $manifest = $json($directory . '/composer.json');
        $manifest['repositories'][] = ['type' => 'path', 'url' => $temp . '/dependency', 'options' => ['symlink' => false]];
        $manifest['require']['test/build-fixture'] = '^1.0';
        $writeJson($directory . '/composer.json', $manifest);
        file_put_contents($directory . '/app/Probe.php', '<?php namespace ' . $namespace . '; use BuildFixture\\Widget; use function BuildFixture\\answer; class Probe { public static function run(): array { return [Widget::class, Widget::label(), answer(), \\Module::class, \\GlobalWidget::class, \\globalAnswer(), \\GLOBAL_FIXTURE_VALUE, \\pSQL("host"), \\_PS_VERSION_, \\BuildFixture\\autoloadAnswer()]; } }');
        $filesystem->mkdir([$directory . '/controllers/admin', $directory . '/controllers/front', $directory . '/upgrade']);
        file_put_contents($directory . '/controllers/admin/probe.php', '<?php class Admin' . ucfirst($name) . 'Controller extends ModuleAdminController { public function probe() { return \\BuildFixture\\Widget::label(); } }');
        file_put_contents($directory . '/controllers/front/probe.php', '<?php class ' . ucfirst($name) . 'ProbeModuleFrontController extends ModuleFrontController {}');
        $upgrade = $suffix === 'One' ? 'upgrade_module_1_0_1' : 'upgrade_module_1_0_2';
        file_put_contents($directory . '/upgrade/upgrade-1.0.1.php', '<?php function ' . $upgrade . '($module) { return \\BuildFixture\\Widget::label(); }');
        file_put_contents($directory . '/index.php', '<?php $legitimateRootIndex = true;');
        file_put_contents($directory . '/.env', 'PRIVATE=must-not-ship');
        $filesystem->mkdir($directory . '/views/assets');
        $writeJson($directory . '/views/assets/manifest.json', ['_dev/js/front.js' => ['file' => 'front-' . $name . '.js']]);
        file_put_contents($directory . '/views/assets/front-' . $name . '.js', '// Asset isolation fixture');
        $run(['composer', 'install', '--no-interaction', '--prefer-dist'], $directory);
        $snapshot = [];
        foreach (['composer.json', 'composer.lock', 'vendor/autoload.php', 'vendor/test/build-fixture/src/Widget.php', 'app/Probe.php'] as $path) {
            $snapshot[$path] = hash_file('sha256', $directory . '/' . $path);
        }
        $run([PHP_BINARY, 'bin/module', 'build'], $directory);
        foreach ($snapshot as $path => $hash) {
            $check($hash === hash_file('sha256', $directory . '/' . $path), 'Build mutated ' . $path);
        }
        $dist = $directory . '/dist/' . $name;
        $check(file_get_contents($dist . '/copyright.txt') === $copyright . "\n", 'Custom copyright file was not shipped.');
        $check(str_contains(file_get_contents($dist . '/app/Probe.php'), $copyright), 'Build stamping did not use the module copyright.');
        $check(str_contains(file_get_contents($dist . '/config/index.php'), $copyright), 'Build guards did not use the module copyright.');
        foreach (Finder::create()->directories()->in($dist)->append([$dist]) as $shippedDirectory) {
            $check(is_file($shippedDirectory . '/index.php'), 'Missing directory guard: ' . $shippedDirectory);
        }
        $legitimateRootIndex = false;
        require $dist . '/index.php';
        $check($legitimateRootIndex, 'The existing module root index behavior was not shipped.');
        $firstFiles = iterator_count(Finder::create()->files()->ignoreDotFiles(false)->in($dist));
        $run([PHP_BINARY, 'bin/module', 'build'], $directory);
        $check($firstFiles === iterator_count(Finder::create()->files()->ignoreDotFiles(false)->in($dist)), 'Repeated build accumulated generated files.');
        $zip = new ZipArchive();
        $check($zip->open($directory . '/dist/' . $name . '.zip') === true, 'Archive not generated.');
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entry = $zip->getNameIndex($i);
            $check(str_starts_with($entry, $name . '/'), 'Wrong ZIP root.');
            $check(!in_array(basename($entry), ['.php-cs-fixer.dist.php', '.gitignore', '.gitattributes', '.DS_Store'], true), 'Development metadata shipped: ' . $entry);
            $check(!preg_match('~(?:^|/)(?:tools|tests|node_modules|\.env|\.git)(?:/|$)~', $entry), 'Development file shipped: ' . $entry);
        }
        $zip->close();
        $check(is_file($dist . '/vendor/test/build-fixture/src/asset.json'), 'Dependency resource lost.');
        $check(is_file($dist . '/vendor/dartmoon/prestashop-module-toolkit/resources/js/forms.js'), 'Toolkit form JavaScript resource lost.');
        $check(is_file($dist . '/config/routes.yml') && is_file($dist . '/views/templates/admin/example.html.twig'), 'Back-office example missing from release.');
        $check(is_file($dist . '/controllers/front/example.php') && is_file($dist . '/views/templates/front/example.tpl'), 'Front-office example missing from release.');
        $check(!is_dir($dist . '/vendor/dartmoon/prestashop-module-toolkit/vendor'), 'Nested development vendor shipped.');
        $archiveHash = hash_file('sha256', $directory . '/dist/' . $name . '.zip');
        // A failed child process must fail the build and retain the previous archive.
        file_put_contents($directory . '/scoper.inc.php', '<?php return ["unsupported-option" => true];');
        $run([PHP_BINARY, 'bin/module', 'build'], $directory, false);
        $check($archiveHash === hash_file('sha256', $directory . '/dist/' . $name . '.zip'), 'Failed build replaced the last good archive.');
        $modules[] = [$dist, $namespace, $directory];
    }
    // A fresh PHP process proves that no source autoloader masks missing or
    // conflicting scoped classes/functions. Both modules share this process.
    $script = '<?php define("_PS_VERSION_", "9.2.0"); class Module {} class ModuleAdminController {} class ModuleFrontController {} function pSQL($value) { return $value; } ';
    foreach ($modules as [$dist, $namespace]) {
        $script .= 'require ' . var_export($dist . '/vendor/autoload.php', true) . ';';
        $script .= 'require ' . var_export($dist . '/' . basename($dist) . '.php', true) . '; if (!is_subclass_of(' . var_export(ucfirst(basename($dist)), true) . ', "Module")) { throw new RuntimeException("Global module entry class was scoped incorrectly"); }';
        $script .= 'require ' . var_export($dist . '/controllers/admin/probe.php', true) . '; if ((new \\Admin' . ucfirst(basename($dist)) . 'Controller())->probe() !== "isolated") { throw new RuntimeException("Legacy admin controller was scoped incorrectly"); }';
        $script .= 'require ' . var_export($dist . '/controllers/front/probe.php', true) . '; if (!class_exists(' . var_export(ucfirst(basename($dist)) . 'ProbeModuleFrontController', true) . ', false)) { throw new RuntimeException("Legacy front controller was scoped incorrectly"); }';
        $script .= 'require ' . var_export($dist . '/controllers/front/example.php', true) . '; if (!class_exists(' . var_export(ucfirst(basename($dist)) . 'ExampleModuleFrontController', true) . ', false)) { throw new RuntimeException("Example front controller was scoped incorrectly"); }';
        $upgrade = str_ends_with($namespace, 'One') ? 'upgrade_module_1_0_1' : 'upgrade_module_1_0_2';
        $script .= 'require ' . var_export($dist . '/upgrade/upgrade-1.0.1.php', true) . '; if (' . $upgrade . '(new Module()) !== "isolated") { throw new RuntimeException("Upgrade function was scoped incorrectly"); }';
        $script .= '$result = \\' . $namespace . '\\Probe::run(); if ($result !== [' . var_export($namespace . '\\Dependencies\\BuildFixture\\Widget', true) . ', "isolated", 42, "Module", ' . var_export($namespace . '\\Dependencies\\GlobalWidget', true) . ', 7, 11, "host", "9.2.0", 3]) { throw new RuntimeException(json_encode($result)); }';
        $script .= '$config = new \\' . $namespace . '\\Dependencies\\Dartmoon\\Config\\Config(' . var_export($dist . '/config', true) . '); if (!$config->get("module.name")) { throw new RuntimeException("Config failed"); }';
        $script .= '$scope = new \stdClass(); $facade = ' . var_export($namespace . '\\Dependencies\\Dartmoon\\Config\\Facades\\Config', true) . '; $facade::swap($scope, $config); $bindings[] = [$facade, $scope, ' . var_export(basename($dist), true) . '];';
        $script .= '$filters = new \\' . $namespace . '\\Dependencies\\Dartmoon\\Filters\\FilterService(); if ($filters->filter(" <Sample> ", "clean|slugify") !== "sample") { throw new RuntimeException("Scoped filters failed"); }';
        $script .= 'if (\\' . $namespace . '\\Dependencies\\Dartmoon\\Renderers\\HtmlAttributes::render(["data-value" => "<sample>"]) !== \'data-value="&lt;sample&gt;"\') { throw new RuntimeException("Scoped renderer failed"); }';
        $assetUrl = '/modules/' . basename($dist) . '/views/assets/front-' . basename($dist) . '.js';
        $script .= '$assets = new \\' . $namespace . '\\Dependencies\\Dartmoon\\Assets\\AssetManager(' . var_export($dist, true) . ', ' . var_export('/modules/' . basename($dist), true) . '); if ($assets->url("_dev/js/front.js") !== ' . var_export($assetUrl, true) . ' || !str_contains($assets->render("_dev/js/front.js"), \'type="module"\')) { throw new RuntimeException("Scoped asset manager failed"); }';
    }
    $script .= 'foreach ($bindings as [$facade, $scope, $name]) { if ($facade::for($scope)->get("module.name") !== $name) { throw new RuntimeException("Facade state crossed module boundaries"); } }';
    $script .= 'if (class_exists("BuildFixture\\\\Widget", false) || function_exists("BuildFixture\\\\answer") || class_exists("GlobalWidget", false) || function_exists("globalAnswer") || defined("GLOBAL_FIXTURE_VALUE")) { throw new RuntimeException("Unscoped symbols leaked"); } echo "Isolation OK\\n";';
    file_put_contents($temp . '/isolation.php', $script);
    $run([PHP_BINARY, $temp . '/isolation.php'], $temp);
    ++$checks;
    echo "Module pipeline passed ({$checks} checks, two isolated modules).\n";
} finally {
    $filesystem->remove($temp);
}
