<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption(
                'default-migration-table',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The name of the default migration table',
                'phinxlog'
            )->setHelp(sprintf(
                '%sInitializes the application for Phinx%s',
                PHP_EOL,
                PHP_EOL
            ));
    }

    /**
     * Initializes the application.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
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

        // Compute the file paths
        $fileNameConfig = 'phinx.yml'; // TODO - maybe in the future we allow custom config names.
        $fileNameMigrationTableSchema = 'phinxlog.sql';

        $migrationTable = $input->getOption('default-migration-table');

        $defaultMigrationTable = $this->getDefinition()
            ->getOption('default-migration-table')
            ->getDefault();

        if ($migrationTable !== $defaultMigrationTable) {
            $fileNameMigrationTableSchema = $migrationTable . '.sql';
        }

        $filePathConfig = $path . DIRECTORY_SEPARATOR . $fileNameConfig;
        $filePathMigrationTableSchema = $path . DIRECTORY_SEPARATOR . $fileNameMigrationTableSchema;

        if (file_exists($filePathConfig) || file_exists($filePathMigrationTableSchema)) {
            $existingFile = $filePathMigrationTableSchema;
            if (file_exists($filePathConfig)) {
                $existingFile = $filePathConfig;
            }
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $existingFile
            ));
        }

        // load the config and migration table template
        if (is_dir(__DIR__ . '/../../../../templates')) {
            $configContents = file_get_contents(__DIR__ . '/../../../../templates/phinx.yml');
            $migrationTableContent = file_get_contents(__DIR__ . '/../../../../templates/phinxlog.sql');
        } else {
            $configContents = file_get_contents(__DIR__ . '/../../../../phinx.yml');
            $migrationTableContent = file_get_contents(__DIR__ . '/../../../../phinxlog.sql');
        }

        if ($migrationTable !== $defaultMigrationTable) {
            $configContents = str_replace(
                $defaultMigrationTable,
                $migrationTable,
                $configContents
            );
            $migrationTableContent = str_replace(
                $defaultMigrationTable,
                $migrationTable,
                $migrationTableContent
            );
        }

        $createdConfig = file_put_contents($filePathConfig, $configContents);
        $createdMigrationTableSchema = file_put_contents(
            $filePathMigrationTableSchema,
            $migrationTableContent
        );

        if (false === $createdConfig || false === $createdMigrationTableSchema) {
            $nonWriteablePath = $filePathMigrationTableSchema;
            if (false === $createdConfig) {
                $nonWriteablePath = $filePathConfig;
            }
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $nonWriteablePath
            ));
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePathConfig));
        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePathMigrationTableSchema));
    }
}
