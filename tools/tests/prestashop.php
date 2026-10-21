<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

ini_set('zend.exception_ignore_args', '1');

// Optional integration test. Run only against a disposable shop:
// php tools/tests/prestashop.php /path/to/shop /path/to/dist/dmframeworktest
// The fixture must be named dmframeworktest; its database scripts should create
// and drop <db-prefix>dmframeworktest_probe. The test always attempts cleanup.
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';
$shop = realpath($argv[1] ?? '');
$artifact = realpath($argv[2] ?? '');
if (!$shop || !$artifact || !is_file($shop . '/config/config.inc.php') || !is_file($artifact . '/config/module.php')) {
    throw new InvalidArgumentException('Pass a disposable shop and a built dmframeworktest module.');
}
$config = require $artifact . '/config/module.php';
if ($config['name'] !== 'dmframeworktest') {
    throw new InvalidArgumentException('This test accepts only the disposable dmframeworktest fixture.');
}
$manifest = json_decode(file_get_contents($artifact . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$namespace = $manifest['extra']['prestashop-module']['namespace'];
$destination = $shop . '/modules/dmframeworktest';
if (file_exists($destination)) {
    throw new RuntimeException('Test module directory already exists; refusing to replace it.');
}
$filesystem = new Filesystem();
$temp = tempnam(sys_get_temp_dir(), 'dm-shop-test-');
file_put_contents($temp, <<<'PHP'
<?php
ini_set('zend.exception_ignore_args', '1');
require getenv('DM_TEST_SHOP') . '/config/config.inc.php';
$mode = $argv[1];
$check = static function ($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
$installed = Module::isInstalled('dmframeworktest');
if ($mode === 'before') {
    $check(!$installed, 'Test module is already installed.');
    exit;
}
if ($mode === 'after') {
    $check(!$installed, 'Test module remains installed.');
    $check(!Tab::getIdFromClassName('AdminDmFrameworkProbeParent'), 'Parent tab was not removed.');
    $check(!Tab::getIdFromClassName('AdminDmFrameworkProbeChild'), 'Child tab was not removed.');
    $metadata = require getenv('DM_TEST_SHOP') . '/modules/dmframeworktest/config/module.php';
    foreach ($metadata['tabs'] as $tab) {
        $check(!Tab::getIdFromClassName($tab['class_name']), 'Example tab was not removed.');
    }
    $check(!Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL(_DB_PREFIX_ . 'dmframeworktest_probe') . "'"), 'Probe SQL table was not removed.');
    echo "Shop cleanup verified.\n";
    exit;
}
$check($installed, 'Module installation failed.');
$module = Module::getInstanceByName('dmframeworktest');
$check($module instanceof Module, 'Scoped module no longer extends the host Module.');
if ($mode === 'controllers') {
    $kernel = new AdminKernel('prod', false);
    $kernel->boot();
    $container = $kernel->getContainer();
    $class = getenv('DM_TEST_NAMESPACE') . '\\Controllers\\Admin\\ExampleController';
    $controller = $container->get($class);
    $check($controller instanceof PrestaShopBundle\Controller\Admin\PrestaShopAdminController, 'Admin controller service is not available.');
    $method = new ReflectionMethod($controller, 'index');
    $check(count($method->getAttributes(PrestaShopBundle\Security\Attribute\AdminSecurity::class)) === 1, 'Admin read permission check is missing.');
    $frontUrl = Context::getContext()->link->getModuleLink($module->name, 'example');
    // Twig is private in the production container; use the controller's
    // injected service locator, as AbstractController::render() does.
    $property = new ReflectionProperty(Symfony\Bundle\FrameworkBundle\Controller\AbstractController::class, 'container');
    $services = $property->getValue($controller);
    $html = $services->get('twig')->load('@Modules/' . $module->name . '/views/templates/admin/example.html.twig')->renderBlock('content', [
        'module_name' => $module->displayName,
        'front_url' => $frontUrl,
    ]);
    $check(str_contains($html, 'module-admin-example') && str_contains($html, htmlspecialchars($frontUrl, ENT_QUOTES, 'UTF-8')), 'Admin example view did not render its front-office link.');
    require $module->getLocalPath() . 'controllers/front/example.php';
    $frontClass = ucfirst($module->name) . 'ExampleModuleFrontController';
    $check(is_subclass_of($frontClass, ModuleFrontController::class), 'Front controller class was not preserved.');
    $template = Context::getContext()->smarty->createTemplate('module:' . $module->name . '/views/templates/front/example.tpl');
    $template->compileTemplateSource();
    echo "Controller services, permissions, Twig view and front template passed.\n";
    exit;
}
$check(str_contains($module->getContent(), '<div class="panel">'), 'Module configuration template did not render.');
$metadata = require $module->getLocalPath() . '/config/module.php';
foreach ($metadata['tabs'] as $definition) {
    $tab = new Tab((int) Tab::getIdFromClassName($definition['class_name']));
    $check($tab->id > 0 && $tab->module === $module->name, 'Example tab was not installed.');
    $check($tab->route_name === $definition['route_name'], 'Example tab route is incorrect.');
    $check((int) $tab->id_parent === (int) Tab::getIdFromClassName('AdminParentModulesSf'), 'Example tab has the wrong parent.');
    $check($tab->active, 'Example tab is not visible.');
}
foreach (['displayBackOfficeHeader', 'displayHeader'] as $hook) {
    $check($module->isRegisteredInHook($hook), 'Hook not registered: ' . $hook);
    $check(is_string($module->{'hook' . ucfirst($hook)}([])), 'Hook handler failed.');
}
$assetManifest = $module->getLocalPath() . 'views/assets/manifest.json';
if (is_file($assetManifest)) {
    $assets = json_decode(file_get_contents($assetManifest), true, 512, JSON_THROW_ON_ERROR);
    $context = Context::getContext();
    $controller = $context->controller;
    $get = $_GET;
    $post = $_POST;
    try {
        $context->controller = new FrontController();
        $front = $module->hookDisplayHeader([]);
        $_GET['configure'] = $_POST['configure'] = $module->name;
        $back = $module->hookDisplayBackOfficeHeader([]);
        foreach (['front' => $front, 'back' => $back] as $side => $html) {
            $check(str_contains($html, '<script type="module"'), 'JavaScript must load as a module.');
            $check(str_contains($html, '<link rel="stylesheet"'), 'Stylesheet was not loaded.');
            foreach (['scss', 'js'] as $type) {
                $file = $assets['_dev/' . $type . '/' . $side . '.' . $type]['file'];
                $check(is_file($module->getLocalPath() . 'views/assets/' . $file), 'Built asset is missing.');
                $url = htmlspecialchars($module->getPathUri() . 'views/assets/' . $file, ENT_QUOTES, 'UTF-8');
                $check(str_contains($html, $url), 'Hook did not load the asset from the manifest.');
            }
        }
        unset($_GET['configure'], $_POST['configure']);
        $check($module->hookDisplayBackOfficeHeader([]) === '', 'Back-office assets leaked outside the module page.');
    } finally {
        $context->controller = $controller;
        $_GET = $get;
        $_POST = $post;
    }
}
$check(Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL(_DB_PREFIX_ . 'dmframeworktest_probe') . "'"), 'Install SQL did not run.');
$class = getenv('DM_TEST_NAMESPACE') . '\Dependencies\Dartmoon\TabManager\TabManager';
$manager = new $class();
foreach (['AdminDmFrameworkProbeParent', 'AdminDmFrameworkProbeChild'] as $tab) {
    $check(!Tab::getIdFromClassName($tab), 'A probe tab already exists.');
}
$tabs = [
    ['class_name' => 'AdminDmFrameworkProbeParent', 'name' => ['en' => 'Probe', 'it' => 'Prova']],
    ['class_name' => 'AdminDmFrameworkProbeChild', 'name' => 'Child', 'parent_class_name' => 'AdminDmFrameworkProbeParent'],
];
$check($manager->install($tabs, $module), 'Tab installation failed.');
$check($manager->install($tabs, $module), 'Repeated tab installation failed.');
$parent = new Tab((int) Tab::getIdFromClassName('AdminDmFrameworkProbeParent'));
$child = new Tab((int) Tab::getIdFromClassName('AdminDmFrameworkProbeChild'));
$check((int) $child->id_parent === (int) $parent->id && $parent->active, 'Tab parent or visibility incorrect.');
$check(count($parent->name) === count(Language::getLanguages(false)), 'Translations are incomplete.');
$check($module->hookUnknownProbe([]) === null, 'Unknown hook probe failed.');
echo "Real module, hooks, SQL and tab operations passed.\n";
PHP);
$env = ['DM_TEST_SHOP' => $shop, 'DM_TEST_NAMESPACE' => $namespace];
$run = static function (array $command, bool $mustSucceed = true) use ($shop, $env): Process {
    $process = new Process($command, $shop, $env);
    $process->setTimeout(180);
    $process->run();
    if ($mustSucceed && !$process->isSuccessful()) {
        throw new RuntimeException($process->getCommandLine() . "\n" . $process->getOutput() . $process->getErrorOutput());
    }
    if ($process->isSuccessful()) {
        echo $process->getOutput();
    }

    return $process;
};
$console = [PHP_BINARY, '-d', 'memory_limit=1G', $shop . '/bin/console'];
$php = [PHP_BINARY, '-d', 'memory_limit=1G', $temp];
$copied = false;
try {
    $run(array_merge($php, ['before']));
    $filesystem->mirror($artifact, $destination);
    $copied = true;
    $run(array_merge($console, ['prestashop:module', 'install', 'dmframeworktest', '--env=prod', '--no-debug']));
    $run(array_merge($php, ['inspect']));
    // A fresh console process builds the container using the scoped services.php.
    $command = $run(array_merge($console, ['dmframeworktest:hello', '--env=prod', '--no-debug']));
    if (!str_contains($command->getOutput(), 'Module command is ready.')) {
        throw new RuntimeException('Console command exited without executing its handler.');
    }
    $route = $run(array_merge($console, ['debug:router', 'dmframeworktest_admin_example', '--show-controllers', '--env=prod', '--no-debug']));
    if (!str_contains($route->getOutput(), $namespace . '\\Controllers\\Admin\\ExampleController::index')) {
        throw new RuntimeException('Example admin route was not discovered.');
    }
    $run(array_merge($console, ['lint:twig', $destination . '/views/templates/admin/example.html.twig', '--env=prod', '--no-debug']));
    $run(array_merge($php, ['controllers']));
} finally {
    try {
        if ($copied) {
            $cleanup = $run(array_merge($console, ['prestashop:module', 'uninstall', 'dmframeworktest', '--env=prod', '--no-debug']), false);
            if (!$cleanup->isSuccessful()) {
                throw new RuntimeException('Cleanup failed; fixture retained at ' . $destination . "\n" . $cleanup->getErrorOutput());
            }
            $run(array_merge($php, ['after']));
            $filesystem->remove($destination);
        }
    } finally {
        unlink($temp);
    }
}
echo "PrestaShop integration passed.\n";
