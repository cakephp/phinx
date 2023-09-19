<?php
declare(strict_types=1);

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

#[AsCommand(name: 'seed:create')]
class SeedCreate extends AbstractCommand
{
    /**
     * @var string|null
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected static $defaultName = 'seed:create';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Create a new database seeder')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the seeder?')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Specify the path in which to create this seeder')
            ->setHelp(sprintf(
                '%sCreates a new database seeder%s',
                PHP_EOL,
                PHP_EOL
            ));

        // An alternative template.
        $this->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use an alternative template');
    }

    /**
     * Get the confirmation question asking if the user wants to create the
     * seeds directory.
     *
     * @return \Symfony\Component\Console\Question\ConfirmationQuestion
     */
    protected function getCreateSeedDirectoryQuestion(): ConfirmationQuestion
    {
        return new ConfirmationQuestion('Create seeds directory? [y]/n ', true);
    }

    /**
     * Get the question that allows the user to select which seed path to use.
     *
     * @param string[] $paths Paths
     * @return \Symfony\Component\Console\Question\ChoiceQuestion
     */
    protected function getSelectSeedPathQuestion(array $paths): ChoiceQuestion
    {
        return new ChoiceQuestion('Which seeds path would you like to use?', $paths, 0);
    }

    /**
     * Returns the seed path to create the seeder in.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @throws \Exception
     * @return string
     */
    protected function getSeedPath(InputInterface $input, OutputInterface $output): string
    {
        // First, try the non-interactive option:
        $path = $input->getOption('path');

        if (!empty($path)) {
            return $path;
        }

        $paths = $this->getConfig()->getSeedPaths();

        // No paths? That's a problem.
        if (empty($paths)) {
            throw new Exception('No seed paths set in your Phinx configuration file.');
        }

        $paths = Util::globAll($paths);

        if (empty($paths)) {
            throw new Exception(
                'You probably used curly braces to define seed path in your Phinx configuration file, ' .
                'but no directories have been matched using this pattern. ' .
                'You need to create a seed directory manually.'
            );
        }

        // Only one path set, so select that:
        if (count($paths) === 1) {
            return array_shift($paths);
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = $this->getSelectSeedPathQuestion($paths);

        return $helper->ask($input, $output, $question);
    }

    /**
     * Create the new seeder.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return int 0 on success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        // get the seed path from the config
        $path = $this->getSeedPath($input, $output);

        if (!file_exists($path)) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = $this->getCreateSeedDirectoryQuestion();

            if ($helper->ask($input, $output, $question)) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifySeedDirectory($path);

        $path = realpath($path);
        /** @var string|null $className */
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new InvalidArgumentException(sprintf(
                'The seed class name "%s" is invalid. Please use CamelCase format',
                $className
            ));
        }

        // Compute the file path
        $filePath = $path . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            throw new InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                basename($filePath)
            ));
        }

        // Get the alternative template option from the command line.
        $altTemplate = $input->getOption('template');

        // Verify the alternative template file's existence.
        if ($altTemplate && !is_file($altTemplate)) {
            throw new InvalidArgumentException(sprintf(
                'The template file "%s" does not exist',
                $altTemplate
            ));
        }

        // Command-line option must have higher priority than value from Config
        $config = $this->getConfig();
        if (is_null($altTemplate)) {
            $altTemplate = $config->getSeedTemplateFile();
            if (!is_null($altTemplate) && !is_file($altTemplate)) {
                throw new InvalidArgumentException(sprintf(
                    'The template file `%s` from config does not exist',
                    $altTemplate
                ));
            }
        }

        // Determine the appropriate mechanism to get the template
        // Load the alternative template if it is defined.
        $contents = file_get_contents($altTemplate ?: $this->getSeedTemplateFilename());

        $namespace = $config instanceof NamespaceAwareInterface ? $config->getSeedNamespaceByPath($path) : null;
        $classes = [
            '$namespaceDefinition' => $namespace !== null ? (PHP_EOL . 'namespace ' . $namespace . ';' . PHP_EOL) : '',
            '$namespace' => $namespace,
            '$useClassName' => $config->getSeedBaseClassName(false),
            '$className' => $className,
            '$baseClassName' => $config->getSeedBaseClassName(true),
        ];
        $contents = strtr($contents, $classes);

        if (file_put_contents($filePath, $contents) === false) {
            throw new RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>using seed base class</info> ' . $classes['$useClassName'], $this->verbosityLevel);
        $output->writeln('<info>created</info> ' . Util::relativePath($filePath), $this->verbosityLevel);

        return self::CODE_SUCCESS;
    }
}
