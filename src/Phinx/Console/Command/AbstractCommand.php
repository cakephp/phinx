<?php

namespace Phinx\Console\Command;

use Symfony\Component\Config\FileLocator,
    Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Output\OutputInterface,
    Phinx\Config\Config,
    Phinx\Migration\Manager,
    Phinx\Adapter\AdapterInterface;

/**
 * Abstract command, contains bootstrapping info
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    /**
     * @var ArrayAccess
     */
    protected $config;
    
    /**
     * @var \Phinx\Adapter\AdapterInterface
     */
    protected $adapter;
    
    /**
     * @var \Phinx\Migration\Manager;
     */
    protected $manager;
    
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('--configuration', '-c', InputArgument::OPTIONAL, 'The configuration file to load');
    }
    
    /**
     * Bootstrap Phinx.
     *
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input, $output);
        $this->loadManager($output);
        // report the migrations path
        $output->writeln('<info>using migration path</info> ' . $this->getConfig()->getMigrationPath());
    }

    /**
     * Sets the config.
     *
     * @param \ArrayAccess $config
     * @return AbstractCommand
     */
    public function setConfig(\ArrayAccess $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the config.
     *
     * @return \ArrayAccess
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
     * @return \Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Returns config file path
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return string
     */
    protected function locateConfigFile(InputInterface $input)
    {
        $configFile = $input->getOption('configuration');

        if (null === $configFile) {
            $configFile = 'phinx.yml';
        }

        $cwd = getcwd();

        // locate the phinx config file (default: phinx.yml)
        // TODO - In future walk the tree in reverse (max 10 levels)
        $locator = new FileLocator(array(
            $cwd . DIRECTORY_SEPARATOR
        ));

        // Locate() throws an exception if the file does not exist
        return $locator->locate($configFile, $cwd, $first = true);
    }

    /**
     * Parse the config file and load it into the config object
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $configFilePath = $this->locateConfigFile($input);
        $output->writeln('<info>using config</info> .' . str_replace(getcwd(), '', realpath($configFilePath)));
        $this->setConfig(Config::fromYaml($configFilePath));
    }

    /**
     * Load the migrations manager and inject the config
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function loadManager(OutputInterface $output)
    {
        if (null === $this->getManager()) {
            $manager = new Manager($this->getConfig(), $output);
            $this->setManager($manager);
        }
    }
}