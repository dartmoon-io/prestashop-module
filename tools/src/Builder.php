<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ModuleTools;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final class Builder
{
    private Filesystem $filesystem;

    public function __construct(private string $root)
    {
        $this->filesystem = new Filesystem();
    }

    public function build(array $options): void
    {
        $manifest = json_decode(file_get_contents($this->root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $metadata = $manifest['extra']['prestashop-module'];
        $name = $metadata['name'];
        if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $name) || $name === 'examplemodule' || !is_file($this->root . '/' . $name . '.php')) {
            throw new \RuntimeException('Configure the module before building: php bin/module setup');
        }
        if (!is_file($this->root . '/composer.lock')) {
            throw new \RuntimeException('Run composer install and commit composer.lock before building.');
        }
        $output = $options['output-dir'] ?? 'dist';
        if (!$this->filesystem->isAbsolutePath($output)) {
            $output = $this->root . '/' . $output;
        }
        $this->filesystem->mkdir($output);
        $output = realpath($output);
        if ($output === $this->root || str_starts_with($this->root . '/', $output . '/')) {
            throw new \InvalidArgumentException('The output directory must not be the project root or its parent.');
        }
        if (isset($options['assets'])) {
            $this->run(['npm', 'run', 'build'], $this->root);
        }
        $temp = sys_get_temp_dir() . '/prestashop-module-' . bin2hex(random_bytes(8));
        $stage = $temp . '/source';
        $scoped = $temp . '/scoped';
        $this->filesystem->mkdir($stage);
        try {
            $this->run($this->composer(['validate', '--strict', '--no-check-publish']), $this->root);
            $this->copySource($stage, $output);
            ReleasePolicy::stamp($stage);
            // Resolve local workspace repositories from the original module, not /tmp.
            // Locks keep the same URL; Composer resolves relative path URLs against cwd.
            // Install through COMPOSER_VENDOR_DIR into the stage without mutating the source.
            $env = ['COMPOSER_VENDOR_DIR' => $stage . '/vendor', 'COMPOSER_MIRROR_PATH_REPOS' => '1'];
            $this->run($this->composer(['install', '--no-dev', '--no-scripts', '--no-plugins', '--prefer-dist', '--no-interaction']), $this->root, $env);
            $this->pruneDependencyDevelopmentFiles($stage);
            $this->scopeDependencies($stage, $scoped, $temp, $metadata);
            ReleasePolicy::protect($scoped);
            $destination = $output . '/' . $name;
            // Publish only after every command succeeds. Keep a previous artifact on failure.
            $pending = $output . '/.' . $name . '-' . bin2hex(random_bytes(6));
            try {
                $this->filesystem->mirror($scoped, $pending);
                if (!isset($options['no-zip'])) {
                    $archive = $temp . '/' . $name . '.zip';
                    $this->zip($scoped, $archive, $name);
                    $this->filesystem->copy($archive, $pending . '.zip');
                }
                $replacements = [[$pending, $destination]];
                if (!isset($options['no-zip'])) {
                    $replacements[] = [$pending . '.zip', $output . '/' . $name . '.zip'];
                }
                $this->publish($replacements);
            } finally {
                $this->filesystem->remove([$pending, $pending . '.zip']);
            }
            fwrite(STDOUT, "Built {$destination}" . (isset($options['no-zip']) ? '' : " and {$output}/{$name}.zip") . "\n");
        } finally {
            $this->filesystem->remove($temp);
        }
    }

    /** Transform staged dependencies and regenerate Composer's production loader. */
    private function scopeDependencies(string $stage, string $scoped, string $temp, array $metadata): void
    {
        $override = $this->root . '/scoper.inc.php';
        if (is_file($override)) {
            $custom = require $override;
            if (!is_array($custom)) {
                throw new \RuntimeException('scoper.inc.php must return an array of overrides.');
            }
        }
        // Run PHP-Scoper via its supported CLI. Do not depend on its internal Container API.
        $configPath = $temp . '/scoper.php';
        // A small bridge loads the same code and optional overrides in the CLI process.
        $bridge = '<?php require ' . var_export($this->root . '/tools/vendor/autoload.php', true) . ';' . "\n";
        $bridge .= '$config = \\Dartmoon\\ModuleTools\\ScoperConfig::forModule(' . var_export($stage, true) . ');' . "\n";
        if (is_file($override)) {
            $bridge .= '$custom = require ' . var_export($override, true) . ';' . "\n";
            $bridge .= 'foreach ($custom as $key => $value) { $config[$key] = in_array($key, ["exclude-namespaces", "exclude-classes", "exclude-functions", "exclude-constants", "patchers", "exclude-files"], true) ? array_merge($config[$key] ?? [], $value) : $value; }' . "\n";
        }
        $bridge .= 'return $config;';
        $this->filesystem->dumpFile($configPath, $bridge);
        $this->run([PHP_BINARY, $this->root . '/tools/vendor/bin/php-scoper', 'add-prefix', '--config=' . $configPath, '--output-dir=' . $scoped, '--force', '--no-interaction'], $stage);

        // Composer must regenerate autoload.php AND autoload_files.php from scoped
        // dependency metadata. Never classmap an unscoped and a scoped vendor together.
        $scopedManifest = json_decode(file_get_contents($scoped . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        unset($scopedManifest['require-dev'], $scopedManifest['autoload-dev'], $scopedManifest['scripts'], $scopedManifest['repositories']);
        $scopedManifest['config']['allow-plugins'] = false;
        $scopedManifest['config']['autoloader-suffix'] = substr(hash('sha256', $metadata['namespace'] . '\\' . $metadata['name']), 0, 32);
        $this->filesystem->dumpFile($scoped . '/composer.json', json_encode($scopedManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        // installed.json is excluded from scoping with vendor/composer. Copy fresh
        // metadata, prefixing autoload entries via PHP-Scoper's rewritten package manifests.
        $this->rebuildInstalledMetadata($stage, $scoped);
        $this->run($this->composer(['dump-autoload', '--no-dev', '--no-scripts', '--no-plugins', '--optimize', '--strict-psr']), $scoped);
        $this->isolateAutoloadFileIdentifiers($scoped, $metadata['namespace']);
    }

    /** Restore both previous outputs if any final publication operation fails. */
    private function publish(array $replacements): void
    {
        $backups = [];
        $published = [];
        try {
            foreach ($replacements as [$pending, $destination]) {
                if (file_exists($destination)) {
                    $backup = $pending . '.previous';
                    $this->filesystem->rename($destination, $backup);
                    $backups[$destination] = $backup;
                }
                $this->filesystem->rename($pending, $destination);
                $published[] = $destination;
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($published) as $destination) {
                $this->filesystem->remove($destination);
            }
            foreach ($backups as $destination => $backup) {
                $this->filesystem->rename($backup, $destination);
            }
            throw $exception;
        }
        $this->filesystem->remove(array_values($backups));
    }

    private function copySource(string $stage, string $output): void
    {
        // A release allowlist prevents .env files, tooling, Git metadata and arbitrary
        // development directories from reaching the archive. Module-specific additions
        // can be declared in extra.prestashop-module.include.
        $manifest = json_decode(file_get_contents($this->root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $paths = array_merge([
            $manifest['extra']['prestashop-module']['name'] . '.php', 'composer.json',
            'app', 'src', 'config', 'controllers', 'views', 'database', 'sql', 'upgrade',
            'translations', 'mails', 'override', 'public', 'logo.png', 'logo.gif', 'logo.svg',
            'LICENSE', 'copyright.txt', 'README.md', '.htaccess', 'index.php',
        ], $manifest['extra']['prestashop-module']['include'] ?? []);
        foreach (array_unique($paths) as $path) {
            $source = realpath($this->root . '/' . $path);
            if ($source === false) {
                continue;
            }
            if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || !str_starts_with($source, $this->root . '/') || $source === $output || str_starts_with($source, $output . '/')) {
                throw new \InvalidArgumentException('Invalid release include: ' . $path);
            }
            if (is_dir($source)) {
                $finder = Finder::create()->files()->ignoreDotFiles(false)->in($source);
                foreach ($finder as $file) {
                    if (str_starts_with($file->getPathname(), $output . '/')) {
                        continue;
                    }
                    if ($file->isLink() || preg_match('~(?:^|/)(?:\.env(?:\..*)?|\.git|node_modules|vendor)(?:/|$)~', $file->getRelativePathname())) {
                        throw new \RuntimeException('Unsafe release file: ' . $file->getPathname());
                    }
                    $this->filesystem->copy($file->getPathname(), $stage . '/' . $path . '/' . $file->getRelativePathname());
                }
            } else {
                $this->filesystem->copy($source, $stage . '/' . $path);
            }
        }
    }

    private function rebuildInstalledMetadata(string $stage, string $scoped): void
    {
        $this->filesystem->mirror($stage . '/vendor/composer', $scoped . '/vendor/composer');
        $file = $scoped . '/vendor/composer/installed.json';
        $installed = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        foreach ($installed['packages'] as &$package) {
            $packageFile = $scoped . '/vendor/' . $package['name'] . '/composer.json';
            if (is_file($packageFile)) {
                $scopedPackage = json_decode(file_get_contents($packageFile), true, 512, JSON_THROW_ON_ERROR);
                $package['autoload'] = $scopedPackage['autoload'] ?? [];
            }
        }
        unset($package);
        $this->filesystem->dumpFile($file, json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function pruneDependencyDevelopmentFiles(string $stage): void
    {
        // A Composer path repository mirrors ignored files too. In particular, a
        // toolkit's own vendor/ and tests/ must never become module runtime code.
        $installed = json_decode(file_get_contents($stage . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($installed['packages'] as $package) {
            $base = $stage . '/vendor/' . $package['name'];
            foreach (['vendor', 'node_modules', '.git', '.github', '.phpunit.cache', 'tests', 'Tests', 'test', 'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
                '.php-cs-fixer.dist.php', '.php-cs-fixer.php', '.php-cs-fixer.cache',
                '.gitignore', '.gitattributes', '.editorconfig', '.DS_Store'] as $path) {
                $this->filesystem->remove($base . '/' . $path);
            }
        }
        foreach (Finder::create()->files()->ignoreDotFiles(false)->in($stage . '/vendor')->name(['.env', '.env.*']) as $file) {
            $this->filesystem->remove($file->getPathname());
        }
    }

    private function isolateAutoloadFileIdentifiers(string $root, string $namespace): void
    {
        // Composer hashes package name + relative file path for autoload.files.
        // Two modules bundling the same package otherwise share a global ID and
        // the second module's scoped functions are silently skipped.
        foreach (['autoload_files.php', 'autoload_static.php'] as $file) {
            $path = $root . '/vendor/composer/' . $file;
            if (is_file($path)) {
                $content = preg_replace_callback(
                    "/'([a-f0-9]{32})' =>/",
                    static fn (array $match): string => "'" . md5($namespace . ':' . $match[1]) . "' =>",
                    file_get_contents($path)
                );
                $this->filesystem->dumpFile($path, $content);
            }
        }
    }

    private function composer(array $arguments): array
    {
        $binary = getenv('COMPOSER_BINARY') ?: 'composer';

        // Composer exposes COMPOSER_BINARY when invoking a script from a PHAR.
        return array_merge(is_file($binary) ? [PHP_BINARY, $binary] : [$binary], $arguments);
    }

    private function run(array $command, string $cwd, array $env = []): void
    {
        $process = new Process($command, $cwd, array_replace([
            'COMPOSER' => 'composer.json',
            'COMPOSER_VENDOR_DIR' => false,
            'COMPOSER_BIN_DIR' => false,
        ], $env));
        $process->setTimeout(600);
        $process->mustRun(static function (string $type, string $buffer): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
        });
    }

    private function zip(string $source, string $file, string $name): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create release archive.');
        }
        foreach (Finder::create()->files()->ignoreDotFiles(false)->in($source)->sortByName() as $item) {
            $entry = $name . '/' . $item->getRelativePathname();
            if (!$zip->addFile($item->getPathname(), $entry)) {
                throw new \RuntimeException('Cannot add ' . $entry . ' to release archive.');
            }
            $zip->setExternalAttributesName($entry, \ZipArchive::OPSYS_UNIX, 0100644 << 16);
        }
        if (!$zip->close()) {
            throw new \RuntimeException('Cannot finish release archive.');
        }
    }
}
