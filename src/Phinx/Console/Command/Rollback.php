<?php

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
        
        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }
        
        // rollback the specified environment
        $this->getManager()->rollback($environment, $version);
    }
}