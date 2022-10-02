<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use Exception;
use InvalidArgumentException;
use Phinx\Config\NamespaceAwareInterface;
use Phinx\Util\Util;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'create')]
class Create extends AbstractCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'create';

    /**
     * The name of the interface that any external template creation class is required to implement.
     */
    public const CREATION_INTERFACE = 'Phinx\Migration\CreationInterface';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Create a new migration')
            ->addArgument('name', InputArgument::OPTIONAL, 'Class name of the migration (in CamelCase)')
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

        // Allow the migration path to be chosen non-interactively.
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Specify the path in which to create this migration');
    }

    /**
     * Get the confirmation question asking if the user wants to create the
     * migrations directory.
     *
     * @return \Symfony\Component\Console\Question\ConfirmationQuestion
     */
    protected function getCreateMigrationDirectoryQuestion()
    {
        return new ConfirmationQuestion('Create migrations directory? [y]/n ', true);
    }

    /**
     * Get the question that allows the user to select which migration path to use.
     *
     * @param string[] $paths Paths
     * @return \Symfony\Component\Console\Question\ChoiceQuestion
     */
    protected function getSelectMigrationPathQuestion(array $paths)
    {
        return new ChoiceQuestion('Which migrations path would you like to use?', $paths, 0);
    }

    /**
     * Returns the migration path to create the migration in.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @throws \Exception
     * @return string
     */
    protected function getMigrationPath(InputInterface $input, OutputInterface $output)
    {
        // First, try the non-interactive option:
        $path = $input->getOption('path');

        if (!empty($path)) {
            return $path;
        }

        $paths = $this->getConfig()->getMigrationPaths();

        // No paths? That's a problem.
        if (empty($paths)) {
            throw new Exception('No migration paths set in your Phinx configuration file.');
        }

        $paths = Util::globAll($paths);

        if (empty($paths)) {
            throw new Exception(
                'You probably used curly braces to define migration path in your Phinx configuration file, ' .
                'but no directories have been matched using this pattern. ' .
                'You need to create a migration directory manually.'
            );
        }

        // Only one path set, so select that:
        if (count($paths) === 1) {
            return array_shift($paths);
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = $this->getSelectMigrationPathQuestion($paths);

        return $helper->ask($input, $output, $question);
    }

    /**
     * Create the new migration.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return int 0 on success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        // get the migration path from the config
        $path = $this->getMigrationPath($input, $output);

        if (!file_exists($path)) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = $this->getCreateMigrationDirectoryQuestion();

            if ($helper->ask($input, $output, $question)) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

        $config = $this->getConfig();
        $namespace = $config instanceof NamespaceAwareInterface ? $config->getMigrationNamespaceByPath($path) : null;

        $path = realpath($path);
        $className = $input->getArgument('name');
        if ($className === null) {
            $currentTimestamp = Util::getCurrentTimestamp();
            $className = 'V' . $currentTimestamp;
            $fileName = $currentTimestamp . '.php';
        } else {
            if (!Util::isValidPhinxClassName($className)) {
                throw new InvalidArgumentException(sprintf(
                    'The migration class name "%s" is invalid. Please use CamelCase format.',
                    $className
                ));
            }

            // Compute the file path
            $fileName = Util::mapClassNameToFileName($className);
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new InvalidArgumentException(sprintf(
                'The migration class name "%s%s" already exists',
                $namespace ? $namespace . '\\' : '',
                $className
            ));
        }

        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        // Get the alternative template and static class options from the config, but only allow one of them.
        $defaultAltTemplate = $this->getConfig()->getTemplateFile();
        $defaultCreationClassName = $this->getConfig()->getTemplateClass();
        if ($defaultAltTemplate && $defaultCreationClassName) {
            throw new InvalidArgumentException('Cannot define template:class and template:file at the same time');
        }

        // Get the alternative template and static class options from the command line, but only allow one of them.
        /** @phpstan-var class-string|null $altTemplate */
        $altTemplate = $input->getOption('template');
        /** @phpstan-var class-string|null $creationClassName */
        $creationClassName = $input->getOption('class');
        if ($altTemplate && $creationClassName) {
            throw new InvalidArgumentException('Cannot use --template and --class at the same time');
        }

        // If no commandline options then use the defaults.
        if (!$altTemplate && !$creationClassName) {
            $altTemplate = $defaultAltTemplate;
            $creationClassName = $defaultCreationClassName;
        }

        // Verify the alternative template file's existence.
        if ($altTemplate && !is_file($altTemplate)) {
            throw new InvalidArgumentException(sprintf(
                'The alternative template file "%s" does not exist',
                $altTemplate
            ));
        }

        // Verify that the template creation class (or the aliased class) exists and that it implements the required interface.
        $aliasedClassName = null;
        if ($creationClassName) {
            // Supplied class does not exist, is it aliased?
            if (!class_exists($creationClassName)) {
                $aliasedClassName = $this->getConfig()->getAlias($creationClassName);
                if ($aliasedClassName && !class_exists($aliasedClassName)) {
                    throw new InvalidArgumentException(sprintf(
                        'The class "%s" via the alias "%s" does not exist',
                        $aliasedClassName,
                        $creationClassName
                    ));
                } elseif (!$aliasedClassName) {
                    throw new InvalidArgumentException(sprintf(
                        'The class "%s" does not exist',
                        $creationClassName
                    ));
                }
            }

            // Does the class implement the required interface?
            if (!$aliasedClassName && !is_subclass_of($creationClassName, self::CREATION_INTERFACE)) {
                throw new InvalidArgumentException(sprintf(
                    'The class "%s" does not implement the required interface "%s"',
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            } elseif ($aliasedClassName && !is_subclass_of($aliasedClassName, self::CREATION_INTERFACE)) {
                throw new InvalidArgumentException(sprintf(
                    'The class "%s" via the alias "%s" does not implement the required interface "%s"',
                    $aliasedClassName,
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            }
        }

        // Use the aliased class.
        $creationClassName = $aliasedClassName ?: $creationClassName;

        // Determine the appropriate mechanism to get the template
        if ($creationClassName) {
            // Get the template from the creation class
            $creationClass = new $creationClassName($input, $output);
            $contents = $creationClass->getMigrationTemplate();
        } else {
            // Load the alternative template if it is defined.
            $contents = file_get_contents($altTemplate ?: $this->getMigrationTemplateFilename());
        }

        // inject the class names appropriate to this migration
        $classes = [
            '$namespaceDefinition' => $namespace !== null ? (PHP_EOL . 'namespace ' . $namespace . ';' . PHP_EOL) : '',
            '$namespace' => $namespace,
            '$useClassName' => $this->getConfig()->getMigrationBaseClassName(false),
            '$className' => $className,
            '$version' => Util::getVersionFromFileName($fileName),
            '$baseClassName' => $this->getConfig()->getMigrationBaseClassName(true),
        ];
        $contents = strtr($contents, $classes);

        if (file_put_contents($filePath, $contents) === false) {
            throw new RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        // Do we need to do the post creation call to the creation class?
        if (isset($creationClass)) {
            /** @var \Phinx\Migration\CreationInterface $creationClass */
            $creationClass->postMigrationCreation($filePath, $className, $this->getConfig()->getMigrationBaseClassName());
        }

        $output->writeln('<info>using migration base class</info> ' . $classes['$useClassName'], $this->verbosityLevel);

        if (!empty($altTemplate)) {
            $output->writeln('<info>using alternative template</info> ' . $altTemplate, $this->verbosityLevel);
        } elseif (!empty($creationClassName)) {
            $output->writeln('<info>using template creation class</info> ' . $creationClassName, $this->verbosityLevel);
        } else {
            $output->writeln('<info>using default template</info>', $this->verbosityLevel);
        }

        $output->writeln('<info>created</info> ' . Util::relativePath($filePath), $this->verbosityLevel);

        return self::CODE_SUCCESS;
    }
}
