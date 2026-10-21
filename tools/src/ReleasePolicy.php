<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ModuleTools;

use Symfony\Component\Finder\Finder;

/** Operates only on staged project-owned files; dependencies keep their notices. */
final class ReleasePolicy
{
    public static function stamp(string $root): void
    {
        $notice = self::header($root);
        foreach (Finder::create()->files()->ignoreDotFiles(false)->in($root)
            ->exclude(['vendor', 'node_modules', 'dist', '.git', '.phpunit.cache'])->notPath('views/assets') as $file) {
            $extension = $file->getExtension();
            if (!in_array($extension, ['php', 'js', 'mjs', 'scss', 'css', 'yml', 'yaml', 'twig', 'tpl', ''], true)) {
                continue;
            }
            $content = $file->getContents();
            preg_match('~\A(?:\#![^\r\n]*\R)?(?:<\?php\s*)?(?:/\*.*?\*/|\{[#*].*?[#*]\}|(?:\#[^\n]*\n)+)~s', $content, $preamble);
            if (preg_match('/copyright|@license|SPDX-License-Identifier:/i', $preamble[0] ?? '')) {
                continue;
            }
            $header = "/**\n * " . str_replace("\n", "\n * ", $notice) . "\n */\n";
            if ($extension === 'php' || ($extension === '' && str_starts_with($content, '#!/usr/bin/env php'))) {
                $content = preg_replace('/<\?php\s*/', "<?php\n" . $header . "\n", $content, 1);
            } elseif (in_array($extension, ['js', 'mjs', 'scss', 'css'], true)) {
                $content = $header . $content;
            } elseif (in_array($extension, ['yml', 'yaml'], true)) {
                $content = '# ' . str_replace("\n", "\n# ", $notice) . "\n" . $content;
            } elseif ($extension === 'twig') {
                $content = '{# ' . $notice . " #}\n" . $content;
            } elseif ($extension === 'tpl') {
                $content = '{* ' . $notice . " *}\n" . $content;
            } else {
                continue;
            }
            if (file_put_contents($file->getPathname(), $content) === false) {
                throw new \RuntimeException('Cannot write license header: ' . $file->getPathname());
            }
        }
    }

    public static function protect(string $root): void
    {
        $guard = "<?php\n/**\n * " . str_replace("\n", "\n * ", self::header($root)) . "\n */\n"
            . "// Reject a direct directory request without interrupting PHP includes.\n"
            . 'if (isset($_SERVER["SCRIPT_FILENAME"]) && realpath($_SERVER["SCRIPT_FILENAME"]) === __FILE__) {' . "\n"
            . "    http_response_code(403);\n}\n";
        foreach (Finder::create()->directories()->ignoreDotFiles(false)->in($root)->append([$root]) as $directory) {
            if (!is_file($directory . '/index.php')) {
                if (file_put_contents($directory . '/index.php', $guard) === false) {
                    throw new \RuntimeException('Cannot protect release directory: ' . $directory);
                }
            }
        }
    }

    private static function header(string $root): string
    {
        $path = $root . '/copyright.txt';
        $header = @file_get_contents($path);
        if ($header === false || trim($header) === '') {
            throw new \RuntimeException('Supply a readable, non-empty copyright file: ' . $path);
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $header));
    }
}
