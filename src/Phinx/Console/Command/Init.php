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
              ->addArgument('path', InputArgument::OPTIONAL, 'Which path should we initialize for Phinx?')
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
        // get the migration path from the config
        $path = $input->getArgument('path');
        
        if (null === $path) {
            $path = getcwd();
        }
        
        $path = realpath($path);
        
        if (!is_writeable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" is not writeable',
                $path
            ));
        }
        
        // Compute the file path
        $fileName = 'phinx.yml'; // TODO - maybe in the future we allow custom config names.
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }
        
        // load the config template
        if (is_dir(__DIR__ . '/../../../data/Phinx')) {
            $contents = file_get_contents(__DIR__ . '/../../../data/Phinx/phinx.yml');
        } else {
            $contents = file_get_contents(__DIR__ . '/../../../../phinx.yml');
        }
                
        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}