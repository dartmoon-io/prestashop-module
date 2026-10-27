<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ExampleModule\Filters;

use Dartmoon\Filters\Filters\AbstractFilter;

/** A module-owned filter registered through the host's Symfony container. */
final class Greeting extends AbstractFilter
{
    protected $name = 'greeting';

    public function filter(mixed $value, array $params): string
    {
        return (string) $value . ' Custom module filter is active.';
    }
}
