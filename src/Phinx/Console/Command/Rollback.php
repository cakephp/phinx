<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2013 Rob Morgan
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

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

class Rollback extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputArgument::OPTIONAL, 'The target environment');

        $this->setName('rollback')
             ->setDescription('Rollback the last or to a specific migration')
             ->addOption('--target', '-t', InputArgument::OPTIONAL, 'The version number to rollback to')
             ->setHelp(<<<EOT
The <info>rollback</info> command reverts the last migration, or optionally up to a specific version

<info>phinx rollback -e development</info>
<info>phinx rollback -e development -t 20111018185412</info>
<info>phinx rollback -e development -v</info>

EOT
        );
    }

    /**
     * Rollback the migration.
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');
        $version = $input->getOption('target');
        $isVerbose = (bool) $input->getOption('verbose');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        $output->writeln('<info>using database</info> ' . $envOptions['name']);

        // set verbosity if supplied
        if ($isVerbose) {
            $this->getManager()->setVerbose(true);
        }

        // rollback the specified environment
        $start = microtime(true);
        $this->getManager()->rollback($environment, $version);
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}