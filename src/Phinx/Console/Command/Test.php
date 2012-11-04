<?php

namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Leonid Kuzmin <lndkuzmin@gmail.com>
 */
class Test extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        
        $this->addOption('--environment', '-e', InputArgument::OPTIONAL, 'The target environment');

        $this->setName('test')
             ->setDescription('Verify configuration file')
             ->setHelp(<<<EOT
The <info>test</info> command verifies the YAML configuration file and optionally an environment

<info>phinx test</info>
<info>phinx test -e development</info>

EOT
        );
    }

    /**
     * Verify configuration file
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input, $output);
        $this->loadManager($output);

        $migrationsPath = $this->getConfig()->getMigrationPath();

        // validate if migrations path is valid
        if (!file_exists($migrationsPath)) {
            throw new \RuntimeException('The migrations path is invalid');
        }

        $envName = $input->getOption('environment');
        if ($envName) {
            if (!$this->getConfig()->hasEnvironment($envName)) {
                throw new \InvalidArgumentException(sprintf(
                    'The environment "%s" does not exist',
                    $envName
                ));
            }
            
            $output->writeln(sprintf('<info>validating environment</info> %s', $envName));
            $environment = new \Phinx\Migration\Manager\Environment($envName, $this->getConfig()->getEnvironment($envName));
            // validate environment connection
            $environment->getAdapter()->connect();
        }

        $output->writeln('<info>success!</info>');
    }
}
