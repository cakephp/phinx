<?php

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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

/**
 * Abstract command, contains bootstrapping info
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    public const FORMAT_JSON = 'json';
    public const FORMAT_YML_ALIAS = 'yaml';
    public const FORMAT_YML = 'yml';
    public const FORMAT_PHP = 'php';

    /**
     * The location of the default migration template.
     */
    protected const DEFAULT_MIGRATION_TEMPLATE = '/../../Migration/Migration.template.php.dist';

    /**
     * The location of the default seed template.
     */
    protected const DEFAULT_SEED_TEMPLATE = '/../../Seed/Seed.template.php.dist';

    /**
     * @var \Phinx\Config\ConfigInterface
     */
    protected $config;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * @var \Phinx\Migration\Manager
     */
    protected $manager;

    /**
     * Exit code for when command executes successfully
     * @var int
     */
    public const CODE_SUCCESS = 0;

    /**
     * Exit code for when command hits a non-recoverable error during execution
     * @var int
     */
    public const CODE_ERROR = 1;

    /**
     * Exit code for when status command is run and there are missing migrations
     * @var int
     */
    public const CODE_STATUS_MISSING = 2;

    /**
     * Exit code for when status command is run and there are no missing migations,
     * but does have down migrations
     * @var int
     */
    public const CODE_STATUS_DOWN = 3;

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->addOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load');
        $this->addOption('--parser', '-p', InputOption::VALUE_REQUIRED, 'Parser used to read the config file. Defaults to YAML');
    }

    /**
     * Bootstrap Phinx.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        /** @var \Phinx\Config\ConfigInterface|null $config */
        $config = $this->getConfig();
        if (!$config) {
            $this->loadConfig($input, $output);
        }

        $this->loadManager($input, $output);

        if ($bootstrap = $this->getConfig()->getBootstrapFile()) {
            $output->writeln('<info>using bootstrap</info> .' . str_replace(getcwd(), '', realpath($bootstrap)) . ' ');
            Util::loadPhpFile($bootstrap);
        }

        // report the paths
        $paths = $this->getConfig()->getMigrationPaths();

        $output->writeln('<info>using migration paths</info> ');

        foreach (Util::globAll($paths) as $path) {
            $output->writeln('<info> - ' . realpath($path) . '</info>');
        }

        try {
            $paths = $this->getConfig()->getSeedPaths();

            $output->writeln('<info>using seed paths</info> ');

            foreach (Util::globAll($paths) as $path) {
                $output->writeln('<info> - ' . realpath($path) . '</info>');
            }
        } catch (UnexpectedValueException $e) {
            // do nothing as seeds are optional
        }
    }

    /**
     * Sets the config.
     *
     * @param \Phinx\Config\ConfigInterface $config Config
     *
     * @return $this
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Gets the config.
     *
     * @return \Phinx\Config\ConfigInterface
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the database adapter.
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Adapter
     *
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
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets the migration manager.
     *
     * @param \Phinx\Migration\Manager $manager Manager
     *
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
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Returns config file path
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     *
     * @return string
     */
    protected function locateConfigFile(InputInterface $input)
    {
        $configFile = $input->getOption('configuration');

        $useDefault = false;

        if ($configFile === null || $configFile === false) {
            $useDefault = true;
        }

        $cwd = getcwd();

        // locate the phinx config file (default: phinx.yml)
        // In future walk the tree in reverse (max 10 levels)
        $locator = new FileLocator([
            $cwd . DIRECTORY_SEPARATOR,
        ]);

        if (!$useDefault) {
            // Locate() throws an exception if the file does not exist
            return $locator->locate($configFile, $cwd, $first = true);
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
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $configFilePath = $this->locateConfigFile($input);
        $output->writeln('<info>using config file</info> .' . str_replace(getcwd(), '', realpath($configFilePath)));

        $parser = $input->getOption('parser');

        // If no parser is specified try to determine the correct one from the file extension.  Defaults to YAML
        if ($parser === null) {
            $extension = pathinfo($configFilePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case self::FORMAT_JSON:
                    $parser = self::FORMAT_JSON;
                    break;
                case self::FORMAT_PHP:
                    $parser = self::FORMAT_PHP;
                    break;
                case self::FORMAT_YML_ALIAS:
                case self::FORMAT_YML:
                default:
                    $parser = self::FORMAT_YML;
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

        $output->writeln('<info>using config parser</info> ' . $parser);

        $this->setConfig($config);
    }

    /**
     * Load the migrations manager and inject the config
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @return void
     */
    protected function loadManager(InputInterface $input, OutputInterface $output)
    {
        if ($this->getManager() === null) {
            $manager = new Manager($this->getConfig(), $input, $output);
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
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function verifyMigrationDirectory($path)
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
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function verifySeedDirectory($path)
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
    protected function getMigrationTemplateFilename()
    {
        return __DIR__ . self::DEFAULT_MIGRATION_TEMPLATE;
    }

    /**
     * Returns the seed template filename.
     *
     * @return string
     */
    protected function getSeedTemplateFilename()
    {
        return __DIR__ . self::DEFAULT_SEED_TEMPLATE;
    }
}
