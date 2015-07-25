<?php
/**
 * Phinx
 *
 * (The MIT license)
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
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Reset extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment.');

        $this->setName('reset')
             ->setDescription('Reset the target environment to the base state known by phinx')
             ->addOption('--force', '-f', InputOption::VALUE_NONE, 'Force the reset of a production envrionment.')
             ->setHelp(
<<<EOT
The <info>reset</info> command will completely wipe the database of selected environment
and recreate it to the latest base state known by phinx (i.e - the current state of the
schema.sql file) 

In other words, it will use your version of schema.sql to recreate the database 
structure, as well as add any configured seed data, creating a fresh and clean database 
for your application.

This should be faster than recreating the database directly from migrations, though they 
should both end up with the same database state. 

<comment>WARNING:</comment> This function is not intended to be used on production 
database environments. Do so only at your own risk.

<info>phinx reset -e development</info>
EOT
             );
    }

    /**
     * Reset the database
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        if( preg_match('/pro?d(uction)?/i', $environment) ) {
            if( !$input->getOption('force') ) {
                $output->writeln("<comment>WARNING!</comment> It looks like you're trying to reset the database of a production environment.");
                $output->writeln("Since this will completely drop and recreate the database, we will politely decline to continue.");
                $output->writeln("If you really want to do this, use the <info>--force</info> option.");
                return;
            } else {
                $output->writeln("<comment>WARNING!</comment> Resetting what appears to be a production environment!");
            }
        }
        
        // make absolutely certain
        $endpoint = $this->getManager()->getEnvironment($environment)->getEndpoint();
        $question = new ConfirmationQuestion("<question>Are you sure you want to drop and recreate the $environment database: '$endpoint'?</question> ", false);
        $helper = $this->getHelper('question');
        if (!$input->getOption('no-interaction') and !$helper->ask($input, $output, $question)) {
            $output->writeln("Aborting.");
            return;
        }

        // reset the database
        $this->getManager()->reset($environment);
    }
}
