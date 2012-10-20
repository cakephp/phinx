<?php

namespace Phinx\Console\Command;

use Phinx\Migration\Util,
    Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;
    
class Create extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
     protected function configure()
     {
         parent::configure();
         
         $this->setName('create')
              ->setDescription('Create a new migration')
              ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the migration?')
              ->setHelp(sprintf(
                  '%sCreates a new database migration%s',
                  PHP_EOL,
                  PHP_EOL
              ));
    }

    /**
     * Migrate the database.
     * 
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);
        
        // get the migration path from the config
        $path = $this->getConfig()->getMigrationPath();
        
        if (!is_writeable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" is not writeable',
                $path
            ));
        }
        
        $path = realpath($path);
        $className = $input->getArgument('name');
        
        if (!Util::isValidMigrationClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }
        
        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;
        
        if (file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }
        
        // load the migration template
        $contents = file_get_contents(dirname(__FILE__) . '/../../Migration/Migration.template.php.dist');
        
        // inject the class name
        $contents = str_replace('$className', $className, $contents);
        
        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
