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
             ->addOption('--seed', '-s', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'What is the name of the seeder?')
             ->addOption('--database', '-d', InputOption::VALUE_REQUIRED, 'What is the db reference required?')
             ->setHelp(
                 <<<EOT
The <info>seed:run</info> command runs all available or individual seeders

<info>phinx seed:run -e development</info>
<info>phinx seed:run -e development -s UserSeeder</info>
<info>phinx seed:run -e development -s UserSeeder -s PermissionSeeder -s LogSeeder</info>
<info>phinx seed:run -e development -s UserSeeder -d databaseReference
<info>phinx seed:run -e development -v</info>

EOT
            );
    }

    /**
     * Run database seeders.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $seedSet = $input->getOption('seed');
        $environment = $input->getOption('environment');
        $dbRef = $input->getOption('database');

        if ($environment === null) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        $databases = $this->getConfig()->getStorageConfigs($envOptions);
        $start = microtime(true);

        if (!is_null($dbRef)) {
            $this->getManager()->setDbRef($dbRef);
            $this->outputEnvironmentInfo($databases[$dbRef], $output);
            if (empty($seedSet)) {
                // run all the seed(ers)
                $this->getManager()->seed($environment);
            } else {
                // run seed(ers) specified in a comma-separated list of classes
                foreach ($seedSet as $seed) {
                    $this->getManager()->seed($environment, trim($seed));
                }
            }
        } else {
            foreach ($databases as $adapterOptions) {
                $this->getManager()->setSeeds(null);

                if (isset($adapterOptions['dbRef'])) {
                    $this->getManager()->setDbRef($adapterOptions['dbRef']);
                }

                $this->outputEnvironmentInfo($adapterOptions, $output);

                if (empty($seedSet)) {
                    // run all the seed(ers)
                    $this->getManager()->seed($environment);
                } else {
                    // run seed(ers) specified in a comma-separated list of classes
                    foreach ($seedSet as $seed) {
                        $this->getManager()->seed($environment, trim($seed));
                    }
                }
            }
        }

        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
