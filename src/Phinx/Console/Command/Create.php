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

use Phinx\Migration\CreationInterface;
use Phinx\Migration\Util;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Create extends AbstractCommand
{
    /**
     * The name of the interface that any external template creation class is required to implement.
     */
    const CREATION_INTERFACE = 'Phinx\Migration\CreationInterface';

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

        // An alternative template.
        $this->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use an alternative template');

        // A classname to be used to gain access to the template content as well as the ability to
        // have a callback once the migration file has been created.
        $this->addOption('class', 'l', InputOption::VALUE_REQUIRED, 'Use a class implementing "' . self::CREATION_INTERFACE . '" to generate the template');
    }

    /**
     * Get the confirmation question asking if the user wants to create the
     * migrations directory.
     *
     * @return ConfirmationQuestion
     */
    protected function getCreateMigrationDirectoryQuestion()
    {
        return new ConfirmationQuestion('Create migrations directory? [y]/n ', true);
    }

    /**
     * Migrate the database.
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

        // get the migration path from the config
        $path = $this->getConfig()->getMigrationPath();

        if (!file_exists($path)) {
            $helper   = $this->getHelper('question');
            $question = $this->getCreateMigrationDirectoryQuestion();

            if ($helper->ask($input, $output, $question)) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

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

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        // Get the alternative template and static class options, but only allow one of them.
        $altTemplate = $input->getOption('template');
        $creationClassName = $input->getOption('class');
        if ($altTemplate && $creationClassName) {
            throw new \InvalidArgumentException('Cannot use --template and --class at the same time');
        }

        // Verify the alternative template file's existence.
        if ($altTemplate && !is_file($altTemplate)) {
            throw new \InvalidArgumentException(sprintf(
                'The alternative template file "%s" does not exist',
                $altTemplate
            ));
        }

        // Verify the static class exists and that it implements the required interface.
        if ($creationClassName) {
            if (!class_exists($creationClassName)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" does not exist',
                    $creationClassName
                ));
            }
            if (!is_subclass_of($creationClassName, self::CREATION_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" does not implement the required interface "%s"',
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            }
        }

        // Determine the appropriate mechanism to get the template
        if ($creationClassName) {
            // Get the template from the creation class
            $creationClass = new $creationClassName();
            $contents = $creationClass->getMigrationTemplate();
        } else {
            // Load the alternative template if it is defined.
            $contents = file_get_contents($altTemplate ?: $this->getMigrationTemplateFilename());
        }

        // inject the class names appropriate to this migration
        $classes = array(
            '$useClassName'  => $this->getConfig()->getMigrationBaseClassName(false),
            '$className'     => $className,
            '$baseClassName' => $this->getConfig()->getMigrationBaseClassName(true),
        );
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        // Do we need to do the post creation call to the creation class?
        if ($creationClassName) {
            $creationClass->postMigrationCreation($filePath, $className, $this->getConfig()->getMigrationBaseClassName());
        }

        $output->writeln('<info>using migration base class</info> ' . $classes['$useClassName']);

        if (!empty($altTemplate)) {
            $output->writeln('<info>using alternative template</info> ' . $altTemplate);
        } elseif (!empty($creationClassName)) {
            $output->writeln('<info>using template creation class</info> ' . $creationClassName);
        } else {
            $output->writeln('<info>using default template</info>');
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
