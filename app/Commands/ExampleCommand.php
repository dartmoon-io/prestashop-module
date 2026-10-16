<?php

declare(strict_types=1);

namespace Dartmoon\ExampleModule\Commands;

use Dartmoon\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExampleCommand extends AbstractCommand
{
    protected $name = 'examplemodule:hello';
    protected $description = 'Example module command; inject services through the constructor.';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Module command is ready.');

        return self::SUCCESS;
    }
}
