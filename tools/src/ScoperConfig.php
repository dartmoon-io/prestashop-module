<?php

declare(strict_types=1);

namespace Dartmoon\ModuleTools;

use Symfony\Component\Finder\Finder;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final class ScoperConfig
{
    public static function forModule(string $root): array
    {
        $composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $metadata = $composer['extra']['prestashop-module'];
        $namespace = $metadata['namespace'];
        $symbols = json_decode(file_get_contents(__DIR__ . '/../prestashop-symbols.json'), true, 512, JSON_THROW_ON_ERROR);

        return [
            'prefix' => $namespace . '\\Dependencies',
            'php-version' => '8.1',
            // Root Composer metadata is scoped too, including dependency PSR-4 mappings.
            'finders' => [Finder::create()->files()->ignoreDotFiles(false)->in($root)->exclude(['vendor/composer', 'vendor/bin'])->notPath('/^vendor\\/autoload\\.php$/')],
            'exclude-namespaces' => [
                $namespace,
                'PrestaShop', 'PrestaShopBundle',
                // Keep host interfaces and Symfony services compatible with the shop container.
                'Symfony', 'Doctrine', 'Twig', 'Psr',
            ],
            // Exclude host symbols, rather than every global symbol: third-party
            // global classes and functions still need isolation between modules.
            'exclude-classes' => array_merge($symbols['classes'], self::legacyEntryClasses($root), ['/^' . preg_quote($metadata['name'], '/') . '$/i']),
            'exclude-functions' => array_merge($symbols['functions'], ['/^upgrade_module_\d+(?:_\d+)*$/i']),
            'exclude-constants' => array_merge($symbols['constants'], ['/^(?:_?PS_|__PS_|_DB_|_MYSQL_)/']),
            'patchers' => [static function (string $path, string $prefix, string $content) use ($root): string {
                $upgrade = realpath($root . '/upgrade');
                $file = realpath($path) ?: $path;
                if ($upgrade === false || !str_starts_with($file, $upgrade . DIRECTORY_SEPARATOR)) {
                    return $content;
                }
                // PHP-Scoper excludes function references, but still prefixes their
                // declarations. PrestaShop directly includes upgrade files and calls
                // global upgrade_module_* functions, so restore this file's global
                // namespace after dependency references have been transformed.
                $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($content) ?? [];
                foreach ($nodes as $node) {
                    if ($node instanceof Node\Stmt\Namespace_) {
                        if ($node->name?->toString() === $prefix) {
                            $node->name = null;
                        }
                        $node->setAttribute('kind', Node\Stmt\Namespace_::KIND_BRACED);
                    }
                }

                return (new Standard())->prettyPrintFile($nodes) . "\n";
            }],
            'expose-global-classes' => false,
            'expose-global-functions' => false,
            'expose-global-constants' => false,
        ];
    }

    private static function legacyEntryClasses(string $root): array
    {
        $classes = [];
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser(new NameResolver());
        $finder = new NodeFinder();
        foreach (['controllers', 'override'] as $directory) {
            if (!is_dir($root . '/' . $directory)) {
                continue;
            }
            foreach (Finder::create()->files()->name('*.php')->in($root . '/' . $directory) as $file) {
                $nodes = $traverser->traverse($parser->parse($file->getContents()) ?? []);
                foreach ($finder->findInstanceOf($nodes, Node\Stmt\ClassLike::class) as $node) {
                    $name = isset($node->namespacedName) ? $node->namespacedName->toString() : null;
                    if ($name !== null && !str_contains($name, '\\')) {
                        $classes[] = $name;
                    }
                }
            }
        }

        return $classes;
    }
}
