<?php

/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */

declare(strict_types=1);

namespace Dartmoon\ExampleModule\Commands;

use Dartmoon\Console\AbstractCommand;
use Dartmoon\Filters\FilterService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExampleCommand extends AbstractCommand
{
    protected $name = 'examplemodule:hello';
    protected $description = 'Example module command; inject services through the constructor.';

    public function __construct(private FilterService $filters)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->filters->filter('Module command is ready.', 'greeting'));

        return self::SUCCESS;
    }
}
