<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
Dartmoon\ModuleTools\ReleasePolicy::stamp(realpath($argv[1] ?? dirname(__DIR__)));
