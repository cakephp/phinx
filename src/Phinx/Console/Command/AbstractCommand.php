<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2013 Rob Morgan
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
    // config types
    const TYPE_DEFAULT = 'default';
    const TYPE_LOCAL = 'local';
    
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
        $this->addOption('--local-configuration', '-lc', InputArgument::OPTIONAL, 
            'The local configuration file to load. if found, merges with default configuration file');
        $this->addOption('--parser', '-p', InputArgument::OPTIONAL, 'Parser used to read the config file.  Defaults to YAML');
    }
    
    /**
     * Bootstrap Phinx.
     *
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getConfig()) { 
            $this->loadConfig($input, $output);
        }
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
     * @param string $configType type of config file to locate
     * @return string
     */
    protected function locateConfigFile(InputInterface $input, $configType = self::TYPE_DEFAULT)
    {
        switch ($configType)
        {
            case self::TYPE_DEFAULT:
                $configFile = $input->getOption('configuration');
                if(null === $configFile) {
                    $configFile = 'phinx.yml';
                }
                break;
            case self::TYPE_LOCAL:
                $configFile = $input->getOption('local-configuration');
                if(null === $configFile) {
                    $configFile = 'phinx-local.yml';
                    $configuration = $input->getOption('configuration');
                    
                    // configuration has been set, look for file in same folder as default config
                    if($configuration) {
                       $configFile = dirname($configuration) . '/' . $configFile;
                    }
                }
                break;
        }
        
        $cwd = getcwd();

        // locate the phinx config file (default: phinx.yml or phinx-local.yml for local config file)
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
        $output->writeln('<info>using config file</info> .' . str_replace(getcwd(), '', realpath($configFilePath)));
        
        try
        {
            $localConfigFilePath = $this->locateConfigFile($input, self::TYPE_LOCAL);
            $output->writeln('<info>using local config file</info> .' . str_replace(getcwd(), '', realpath($localConfigFilePath)));
        }
        // suppress file not found exception for local config file
        catch(\InvalidArgumentException $e)
        {
            $localConfigFilePath = false;
        }
        
        $parser = $input->getOption('parser');

        // If no parser is specified try to determine the correct one from the file extension.  Defaults to YAML
        if (null === $parser) {
            $extension = pathinfo($configFilePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case 'php':
                    $parser = 'php';
                    break;
                case 'yml':
                default:
                    $parser = 'yaml';
                    break;
            }
        }

        // parse default config
        $config = $this->parseConfig($parser, $configFilePath);
        $output->writeln('<info>using config parser</info> ' . $parser);
            
        // merge with local config if exists
        if($localConfigFilePath) {
            $config->mergeConfigEnvironments($this->parseConfig($parser, $localConfigFilePath));
            $output->writeln('<info>merged with local config</info> ');
        }
        
        $this->setConfig($config);
    }
    
    /**
     * parse config file
     * 
     * @param string $parser parser to use
     * @param string $configFilePath path to config file
     * @return Config
     * 
     * @throws \InvalidArgumentException if parser is not of expected type
     */
    protected function parseConfig($parser, $configFilePath)
    {
        switch (strtolower($parser)) {
            case 'php':
                $config = Config::fromPHP($configFilePath);
                break;
            case 'yaml':
                $config = Config::fromYaml($configFilePath);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('\'%s\' is not a valid parser.', $parser));
        }
        
        return $config;
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
