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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Migration\Manager;
use Phinx\Db\Adapter\AdapterInterface;

/**
 * Abstract command, contains bootstrapping info
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    /**
     * The location of the default migration template.
     */
    const DEFAULT_MIGRATION_TEMPLATE = '/../../Migration/Migration.template.php.dist';

    /**
     * The location of the default seed template.
     */
    const DEFAULT_SEED_TEMPLATE = '/../../Seed/Seed.template.php.dist';

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load');
        $this->addOption('--parser', '-p', InputOption::VALUE_REQUIRED, 'Parser used to read the config file. Defaults to YAML');
    }

    /**
     * Bootstrap Phinx.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getConfig()) {
            $this->loadConfig($input, $output);
        }

        $this->loadManager($input, $output);
        // report the paths
        $output->writeln('<info>using migration path</info> ' . $this->getConfig()->getMigrationPath());
        try {
            $output->writeln('<info>using seed path</info> ' . $this->getConfig()->getSeedPath());
        } catch (\UnexpectedValueException $e) {
            // do nothing as seeds are optional
        }
    }

    /**
     * Sets the config.
     *
     * @param  ConfigInterface $config
     * @return AbstractCommand
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the config.
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter
     * @return AbstractCommand
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets the migration manager.
     *
     * @param Manager $manager
     * @return AbstractCommand
     */
    public function setManager(Manager $manager)
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * Gets the migration manager.
     *
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Returns config file path
     *
     * @param InputInterface $input
     * @return string
     */
    protected function locateConfigFile(InputInterface $input)
    {
        $configFile = $input->getOption('configuration');

        $useDefault = false;

        if (null === $configFile || false === $configFile) {
            $useDefault = true;
        }

        $cwd = getcwd();

        // locate the phinx config file (default: phinx.yml)
        // TODO - In future walk the tree in reverse (max 10 levels)
        $locator = new FileLocator(array(
            $cwd . DIRECTORY_SEPARATOR
        ));

        if (!$useDefault) {
            // Locate() throws an exception if the file does not exist
            return $locator->locate($configFile, $cwd, $first = true);
        }

        $possibleConfigFiles = array('phinx.php', 'phinx.json', 'phinx.yml');
        foreach ($possibleConfigFiles as $configFile) {
            try {
                return $locator->locate($configFile, $cwd, $first = true);
            } catch (\InvalidArgumentException $exception) {
                $lastException = $exception;
            }
        }
        throw $lastException;
    }

    /**
     * Parse the config file and load it into the config object
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $configFilePath = $this->locateConfigFile($input);
        $output->writeln('<info>using config file</info> .' . str_replace(getcwd(), '', realpath($configFilePath)));

        $parser = $input->getOption('parser');

        // If no parser is specified try to determine the correct one from the file extension.  Defaults to YAML
        if (null === $parser) {
            $extension = pathinfo($configFilePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case 'json':
                    $parser = 'json';
                    break;
                case 'php':
                    $parser = 'php';
                    break;
                case 'yml':
                default:
                    $parser = 'yaml';
            }
        }

        switch (strtolower($parser)) {
            case 'json':
                $config = Config::fromJson($configFilePath);
                break;
            case 'php':
                $config = Config::fromPhp($configFilePath);
                break;
            case 'yaml':
                $config = Config::fromYaml($configFilePath);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('\'%s\' is not a valid parser.', $parser));
        }

        $output->writeln('<info>using config parser</info> ' . $parser);

        $this->setConfig($config);
    }

    /**
     * Load the migrations manager and inject the config
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function loadManager(InputInterface $input, OutputInterface $output)
    {
        if (null === $this->getManager()) {
            $manager = new Manager($this->getConfig(), $input, $output);
            $this->setManager($manager);
        }
    }

    /**
     * Verify that the migration directory exists and is writable.
     *
     * @throws InvalidArgumentException
     * @return void
     */
    protected function verifyMigrationDirectory($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Migration directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Migration directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Verify that the seed directory exists and is writable.
     *
     * @throws InvalidArgumentException
     * @return void
     */
    protected function verifySeedDirectory($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Seed directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
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
