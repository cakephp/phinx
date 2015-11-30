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

use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SeedCreate extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('seed:create')
            ->setDescription('Create a new database seeder')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the seeder?')
            ->setHelp(sprintf(
                '%sCreates a new database seeder%s',
                PHP_EOL,
                PHP_EOL
            ));
    }

    /**
     * Get the confirmation question asking if the user wants to create the
     * seeds directory.
     *
     * @return ConfirmationQuestion
     */
    protected function getCreateSeedDirectoryQuestion()
    {
        return new ConfirmationQuestion('Create seeds directory? [y]/n ', true);
    }

    /**
     * Create the new seeder.
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

        // get the seed path from the config
        $path = $this->getConfig()->getSeedPath();

        if (!file_exists($path)) {
            $helper   = $this->getHelper('question');
            $question = $this->getCreateSeedDirectoryQuestion();

            if ($helper->ask($input, $output, $question)) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifySeedDirectory($path);

        $path = realpath($path);
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The seed class name "%s" is invalid. Please use CamelCase format',
                $className
            ));
        }

        // Compute the file path
        $filePath = $path . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                basename($filePath)
            ));
        }

        // inject the class names appropriate to this seeder
        $contents = file_get_contents($this->getSeedTemplateFilename());
        $classes = array(
            '$useClassName'  => 'Phinx\Seed\AbstractSeed',
            '$className'     => $className,
            '$baseClassName' => 'AbstractSeed',
        );
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>using seed base class</info> ' . $classes['$useClassName']);
        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
