<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\Manager;
use Phinx\Util\Util;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

/**
 * Abstract command, contains bootstrapping info
 */
abstract class AbstractCommand extends Command
{
    public const FORMAT_JSON = 'json';
    public const FORMAT_YML_ALIAS = 'yaml';
    public const FORMAT_YML = 'yml';
    public const FORMAT_PHP = 'php';
    public const FORMAT_DEFAULT = 'php';

    /**
     * The location of the default change migration template.
     */
    protected const DEFAULT_CHANGE_MIGRATION_TEMPLATE = '/../../Migration/Migration.change.template.php.dist';

    /**
     * The location of the default up/down migration template.
     */
    protected const DEFAULT_UP_DOWN_MIGRATION_TEMPLATE = '/../../Migration/Migration.up_down.template.php.dist';

    /**
     * The location of the default seed template.
     */
    protected const DEFAULT_SEED_TEMPLATE = '/../../Seed/Seed.template.php.dist';

    /**
     * @var \Phinx\Config\ConfigInterface|null
     */
    protected ?ConfigInterface $config = null;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * @var \Phinx\Migration\Manager
     */
    protected Manager $manager;

    /**
     * @var int
     */
    protected int $verbosityLevel = OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_NORMAL;

    /**
     * Exit code for when command executes successfully
     *
     * @var int
     */
    public const CODE_SUCCESS = 0;

    /**
     * Exit code for when command hits a non-recoverable error during execution
     *
     * @var int
     */
    public const CODE_ERROR = 1;

    /**
     * Exit code for when status command is run and there are missing migrations
     *
     * @var int
     */
    public const CODE_STATUS_MISSING = 2;

    /**
     * Exit code for when status command is run and there are no missing migations,
     * but does have down migrations
     *
     * @var int
     */
    public const CODE_STATUS_DOWN = 3;

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load');
        $this->addOption('--parser', '-p', InputOption::VALUE_REQUIRED, 'Parser used to read the config file. Defaults to YAML');
        $this->addOption('--no-info', null, InputOption::VALUE_NONE, 'Hides all debug information');
    }

    /**
     * Bootstrap Phinx.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output): void
    {
        if ($input->hasParameterOption('--no-info')) {
            $this->verbosityLevel = OutputInterface::VERBOSITY_VERBOSE;
        }

        if (!$this->hasConfig()) {
            $this->loadConfig($input, $output);
        }

        $this->loadManager($input, $output);

        $bootstrap = $this->getConfig()->getBootstrapFile();
        if ($bootstrap) {
            $output->writeln('<info>using bootstrap</info> ' . Util::relativePath($bootstrap) . ' ', $this->verbosityLevel);
            Util::loadPhpFile($bootstrap, $input, $output, $this);
        }

        // report the paths
        $paths = $this->getConfig()->getMigrationPaths();

        $output->writeln('<info>using migration paths</info> ', $this->verbosityLevel);

        foreach (Util::globAll($paths) as $path) {
            $output->writeln('<info> - ' . realpath($path) . '</info>', $this->verbosityLevel);
        }

        try {
            $paths = $this->getConfig()->getSeedPaths();

            $output->writeln('<info>using seed paths</info> ', $this->verbosityLevel);

            foreach (Util::globAll($paths) as $path) {
                $output->writeln('<info> - ' . realpath($path) . '</info>', $this->verbosityLevel);
            }
        } catch (UnexpectedValueException $e) {
            // do nothing as seeds are optional
        }
    }

    /**
     * Sets the config.
     *
     * @param \Phinx\Config\ConfigInterface $config Config
     * @return $this
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasConfig(): bool
    {
        return $this->config !== null;
    }

    /**
     * Gets the config.
     *
     * @return \Phinx\Config\ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        if ($this->config === null) {
            throw new RuntimeException('No config set yet');
        }

        return $this->config;
    }

    /**
     * Sets the database adapter.
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Adapter
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Sets the migration manager.
     *
     * @param \Phinx\Migration\Manager $manager Manager
     * @return $this
     */
    public function setManager(Manager $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Gets the migration manager.
     *
     * @return \Phinx\Migration\Manager|null
     */
    public function getManager(): ?Manager
    {
        return $this->manager ?? null;
    }

    /**
     * Returns config file path
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @return string
     */
    protected function locateConfigFile(InputInterface $input): string
    {
        $configFile = $input->hasOption('configuration') ? $input->getOption('configuration') : null;

        $useDefault = false;

        if ($configFile === null || $configFile === false) {
            $useDefault = true;
        }

        $cwd = getcwd();

        // locate the phinx config file
        // In future walk the tree in reverse (max 10 levels)
        $locator = new FileLocator([
            $cwd . DIRECTORY_SEPARATOR,
        ]);

        if (!$useDefault) {
            // Locate() throws an exception if the file does not exist
            return $locator->locate($configFile, $cwd, true);
        }

        $possibleConfigFiles = ['phinx.php', 'phinx.json', 'phinx.yaml', 'phinx.yml'];
        foreach ($possibleConfigFiles as $configFile) {
            try {
                return $locator->locate($configFile, $cwd, true);
            } catch (InvalidArgumentException $exception) {
                $lastException = $exception;
            }
        }
        throw $lastException;
    }

    /**
     * Parse the config file and load it into the config object
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output): void
    {
        $configFilePath = $this->locateConfigFile($input);
        $output->writeln('<info>using config file</info> ' . Util::relativePath($configFilePath), $this->verbosityLevel);

        /** @var string|null $parser */
        $parser = $input->getOption('parser');

        // If no parser is specified try to determine the correct one from the file extension.  Defaults to YAML
        if ($parser === null) {
            $extension = pathinfo($configFilePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case self::FORMAT_JSON:
                    $parser = self::FORMAT_JSON;
                    break;
                case self::FORMAT_YML_ALIAS:
                case self::FORMAT_YML:
                    $parser = self::FORMAT_YML;
                    break;
                case self::FORMAT_PHP:
                default:
                    $parser = self::FORMAT_DEFAULT;
                    break;
            }
        }

        switch (strtolower($parser)) {
            case self::FORMAT_JSON:
                $config = Config::fromJson($configFilePath);
                break;
            case self::FORMAT_PHP:
                $config = Config::fromPhp($configFilePath);
                break;
            case self::FORMAT_YML_ALIAS:
            case self::FORMAT_YML:
                $config = Config::fromYaml($configFilePath);
                break;
            default:
                throw new InvalidArgumentException(sprintf('\'%s\' is not a valid parser.', $parser));
        }

        $output->writeln('<info>using config parser</info> ' . $parser, $this->verbosityLevel);

        $this->setConfig($config);
    }

    /**
     * Load the migrations manager and inject the config
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return void
     */
    protected function loadManager(InputInterface $input, OutputInterface $output): void
    {
        if (!isset($this->manager)) {
            $manager = new Manager($this->getConfig(), $input, $output);
            $manager->setVerbosityLevel($this->verbosityLevel);
            $container = $this->getConfig()->getContainer();
            if ($container !== null) {
                $manager->setContainer($container);
            }
            $this->setManager($manager);
        } else {
            $manager = $this->getManager();
            $manager->setInput($input);
            $manager->setOutput($output);
        }
    }

    /**
     * Verify that the migration directory exists and is writable.
     *
     * @param string $path Path
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function verifyMigrationDirectory(string $path): void
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf(
                'Migration directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf(
                'Migration directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Verify that the seed directory exists and is writable.
     *
     * @param string $path Path
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function verifySeedDirectory(string $path): void
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf(
                'Seed directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf(
                'Seed directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Returns the migration template filename.
     *
     * @return string
     */
    protected function getMigrationTemplateFilename(string $style): string
    {
        return $style === Config::TEMPLATE_STYLE_CHANGE ? __DIR__ . self::DEFAULT_CHANGE_MIGRATION_TEMPLATE : __DIR__ . self::DEFAULT_UP_DOWN_MIGRATION_TEMPLATE;
    }

    /**
     * Returns the seed template filename.
     *
     * @return string
     */
    protected function getSeedTemplateFilename(): string
    {
        return __DIR__ . self::DEFAULT_SEED_TEMPLATE;
    }
}
