<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Console
 */
namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedRun extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment');

        $this->setName('seed:run')
             ->setDescription('Run database seeders')
             ->addOption('--seed', '-s', InputOption::VALUE_REQUIRED, 'What is the name of the seeder?')
             ->setHelp(
<<<EOT
The <info>seed:run</info> command runs all available or individual seeders

<info>phinx seed:run -e development</info>
<info>phinx seed:run -e development -s UserSeeder</info>
<info>phinx seed:run -e development -v</info>

EOT
             );
    }

    /**
     * Run database seeders.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $seed        = $input->getOption('seed');
        $environment = $input->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        }

        if (isset($envOptions['wrapper'])) {
            $output->writeln('<info>using wrapper</info> ' . $envOptions['wrapper']);
        }

        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');
            return;
        }

        if (isset($envOptions['table_prefix'])) {
            $output->writeln('<info>using table prefix</info> ' . $envOptions['table_prefix']);
        }
        if (isset($envOptions['table_suffix'])) {
            $output->writeln('<info>using table suffix</info> ' . $envOptions['table_suffix']);
        }

        // run the seed(ers)
        $start = microtime(true);
        $this->getManager()->seed($environment, $seed);
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
