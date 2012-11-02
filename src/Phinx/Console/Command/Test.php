<?php
/**
 * @author Leonid Kuzmin <lndkuzmin@gmail.com>
 */

namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

class Test extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('test')
            ->setDescription('Verify configuration file')
            ->setHelp(<<<EOT
The <info>test</info> command verifies the YAML configuration file and prints errors if any

<info>phinx test</info>
EOT
        );
    }

    /**
     * Verify configuration file
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input, $output);
        $this->loadManager($output);

        $migrationsPath = $this->getConfig()->getMigrationPath();

        //validate if migrations path is valid
        if(!file_exists($migrationsPath)) {
            throw new \RuntimeException("Migrations path is invalid");
        }

        $environments = $this->getConfig()->getEnvironments();

        if(count($environments)) {
            //validate if default environment is set
            $output->writeln('<info>validating default environment</info>');
            $defaultEnvironment = $this->getConfig()->getDefaultEnvironment();

            //validate environments
            foreach($environments as $name => $env) {
                $output->writeln(sprintf("<info>validating environment '%s'</info>", $name));
                $environment = new \Phinx\Migration\Manager\Environment($name, $this->getConfig()->getEnvironment($name));
                //validate environment connection
                $environment->getAdapter()->connect();
            }
        } else {
            throw new \RuntimeException("No configuration environments found");
        }

        $output->writeln('<info>success!</info>');
    }
}
