<?php

namespace Phinx\Console\Command;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;
	
class Init extends Command
{
	/**
	 * {@inheritdoc}
	 */
	 protected function configure()
     {
		 $this->setName('init')
			  ->setDescription('Initialize the application for Phinx')
			  ->setHelp(sprintf(
				  '%sInitializes the application for Phinx%s',
				  PHP_EOL,
				  PHP_EOL
			  ));
    }

	/**
	 * Initializes the application.
     * 
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$result = 'init';
		
		// TODO - create YAML config file
		
		$output->write($result);
	}
}