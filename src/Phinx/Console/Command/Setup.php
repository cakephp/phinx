<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
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
use Symfony\Component\Console\Output\OutputInterface;

class Setup extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        
        $this->setName('setup')
            ->setDescription('Create the migration and seed directories');
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
        $this->bootstrap($input, $output);

        $this->createMigrationsDirectory();

        $this->createSeedsDirectory();

        $output->writeln('<info>create migration & seed directories</info>');
    }

    /**
     * Create the migrations directory.
     * 
     * @return void
     */
    protected function createMigrationsDirectory()
    {
        $migrationPath = $this->getConfig()->getMigrationPath();

        if (! file_exists($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        $this->verifyMigrationDirectory($migrationPath);

        $this->putGitKeep($migrationPath);
    }

    /**
     * Create the seeds directory.
     * 
     * @return void
     */
    protected function createSeedsDirectory()
    {
        $seedPath = $this->getConfig()->getSeedPath();

        if (! file_exists($seedPath)) {
            mkdir($seedPath, 0755, true);
        }

        $this->verifySeedDirectory($seedPath);

        $this->putGitKeep($seedPath);

        $this->putDatabaseSeeder($seedPath);
    }

    /**
     * Put a gitkeep file inside the migrations directory.
     * 
     * @return void
     */
    protected function putGitKeep($directoryPath)
    {
        touch(rtrim($directoryPath, '/') . "/.gitkeep");
    }

    /**
     * Put the default database seeder file inside the seeds directory.
     * 
     * @return void
     */
    protected function putDatabaseSeeder($seedPath)
    {
        $filePath = rtrim($seedPath, '/') . "/DatabaseSeeder.php";

        if (! file_exists($filePath)) {

            $contents = file_get_contents($this->getDatabaseSeederTemplateFilename());

            $classes = array(
                '$useClassName'  => 'Phinx\Seed\AbstractSeed',
                '$className'     => 'DatabaseSeeder',
                '$baseClassName' => 'AbstractSeed',
            );

            $contents = strtr($contents, $classes);
            
            if (false === file_put_contents($filePath, $contents)) {
                throw new \RuntimeException(sprintf(
                    'The file "%s" could not be written to',
                    $filePath
                ));
            }
        }
    }
}
