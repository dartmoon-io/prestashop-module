<?php

declare(strict_types=1);

// Maintainer utility. Parse a clean shop without booting it or touching its DB.
require __DIR__ . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

$shop = realpath($argv[1] ?? '');
if (!$shop || !is_file($shop . '/classes/module/Module.php')) {
    throw new InvalidArgumentException('Pass the root of a clean PrestaShop installation.');
}
$symbols = ['classes' => [], 'functions' => [], 'constants' => []];
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$traverser = new NodeTraverser(new NameResolver());
$finder = new NodeFinder();
foreach (Finder::create()->files()->name('*.php')->in(array_map(static fn ($dir) => $shop . '/' . $dir, ['classes', 'controllers', 'config', 'src'])) as $file) {
    $nodes = $traverser->traverse($parser->parse($file->getContents()) ?? []);
    foreach ($finder->find($nodes, static fn (Node $node) => $node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_) as $node) {
        $name = isset($node->namespacedName) ? $node->namespacedName->toString() : null;
        if (!$name || str_contains($name, '\\')) { continue; }
        $key = $node instanceof Node\Stmt\Function_ ? 'functions' : 'classes';
        $symbols[$key][$name] = true;
        if ($key === 'classes' && str_ends_with($name, 'Core')) {
            $symbols['classes'][substr($name, 0, -4)] = true;
        }
    }
    foreach ($finder->findInstanceOf($nodes, Node\Expr\FuncCall::class) as $node) {
        if ($node->name instanceof Node\Name && strtolower($node->name->toString()) === 'define' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
            $name = $node->args[0]->value->value;
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) { $symbols['constants'][$name] = true; }
        }
    }
}
// Host libraries such as Smarty also expose global classes through Composer.
foreach (array_keys(require $shop . '/vendor/composer/autoload_classmap.php') as $class) {
    if (!str_contains($class, '\\')) { $symbols['classes'][$class] = true; }
}
foreach ($symbols as &$values) {
    $values = array_values(array_filter(array_keys($values), static fn (string $name): bool => (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)));
    sort($values);
}
unset($values);
file_put_contents(__DIR__ . '/prestashop-symbols.json', json_encode($symbols, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo 'Saved ' . array_sum(array_map('count', $symbols)) . " PrestaShop host symbols.\n";
