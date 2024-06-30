<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cron\Console;

use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cron extends Command implements \FOSSBilling\InjectionAwareInterface
{
    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    protected function configure(): void
    {
        $this->setName('cron:run');
        $this->addArgument('interval', InputArgument::OPTIONAL, 'Interval in minutes');
        $this->setDescription('Executes the cron jobs');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = $this->di['mod_service']('cron');
        $interval = $input->getArgument('interval') ?? null;

        $output->writeln([
            '<info>FOSSBilling Cron Job Runner</info>',
            '============',
            'Last executed: ' . $service->getLastExecutionTime(),
            '',
        ]);

        try {
            $service->runCrons($interval);
        } catch (\Exception $e) {
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } finally {
            $output->writeln('<info>Successfully ran the cron jobs.</info>');

            return Command::SUCCESS;
        }
    }
}
