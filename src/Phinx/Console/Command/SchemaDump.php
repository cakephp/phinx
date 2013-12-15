<?php

namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class SchemaDump extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputArgument::OPTIONAL, 'The target environment');

        $this->setName('schema-dump')
            ->setDescription('Dump existing database to initial migration');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
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

        $envOptions = $this->getConfig()->getEnvironment($environment);
        $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        $output->writeln('<info>using database</info> ' . $envOptions['name']);

        $path = $this->getConfig()->getMigrationPath();
        $schemaPath = $path.DIRECTORY_SEPARATOR.'schema';

        $fs = new Filesystem();
        if (!$fs->exists($schemaPath)) {
            if (!is_writeable($path)) {
                throw new \InvalidArgumentException(
                    sprintf('The directory "%s" is not writeable', $path)
                );
            }
            $fs->mkdir($schemaPath);
        }

        if (!is_writeable($schemaPath)) {
            throw new \InvalidArgumentException(
                sprintf('The directory "%s" is not writeable', $schemaPath)
            );
        }
        $schemaPath = realpath($schemaPath);
        $fileName = 'schema.'.$environment.'.php';
        $filePath = $schemaPath . DIRECTORY_SEPARATOR . $fileName;

        $start = microtime(true);
        $dump = $this->getManager()->schemaDump($environment);
        $end = microtime(true);

        if (!$dump) {
            $output->writeln('<comment>Database is empty. Nothing to dump!</comment>');

            return;
        }

        if (false === file_put_contents($filePath, $dump)) {
            throw new \RuntimeException(
                sprintf('The file "%s" could not be written to', $path)
            );
        }

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
} 
