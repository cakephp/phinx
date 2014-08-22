<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
 * @subpackage Phinx\Migration\Manager
 */
namespace Phinx\Migration\Manager;

use Phinx\Migration\Schema\Dumper;
use Phinx\Db\Adapter\SqlServerAdapter;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\PostgresAdapter;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Adapter\ProxyAdapter;
use Phinx\Migration\MigrationInterface;

class Environment
{
    /**
     * @var string
     */
    protected $name;
    
    /**
     * @var array
     */
    protected $options;

    /**
     * @var OutputInterface
     */
    protected $output;
    
    /**
     * @var int
     */
    protected $currentVersion;
    
    /**
     * @var string
     */
    protected $schemaTableName = 'phinxlog';

    /**
     * @var callable[] AdapterInterface factory closures
     */
    protected $adapterFactories = array();

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Class Constructor.
     *
     * @param string $name Environment Name
     * @param array $options Options
     * @return Environment
     */
    public function __construct($name, $options)
    {
        $this->name = $name;
        $this->options = $options;

        foreach (static::defaultAdapterFactories() as $adapterName => $adapterFactoryClosure) {
            $this->registerAdapter($adapterName, $adapterFactoryClosure);
        }
    }

    /**
     * You can register new adapter types, by passing a closure which
     * instantiates and returns an implementation of `AdapterInterface`.
     *
     * @param string    $adapterName
     * @param callable  $adapterFactoryClosure A closure which accepts an Environment parameter and returns an AdapterInterface implementation
     */
    public function registerAdapter($adapterName, $adapterFactoryClosure)
    {
        // TODO When 5.3 support is dropped, the `callable` type hint should be
        // added to the $adapterFactoryClosure paramter, and this test can be removed.
        if (!is_callable($adapterFactoryClosure)) {
            throw new \RuntimeException('Provided adapter factory must be callable and return an object implementing AdapterInterface.');
        }
        $this->adapterFactories[$adapterName] = $adapterFactoryClosure;
    }

    /**
     * Executes the specified migration on this environment.
     *
     * @param MigrationInterface $migration Migration
     * @param string $direction Direction
     * @return void
     */
    public function executeMigration(MigrationInterface $migration, $direction = MigrationInterface::UP)
    {
        $startTime = time();
        $direction = ($direction == MigrationInterface::UP) ? MigrationInterface::UP : MigrationInterface::DOWN;
        $migration->setAdapter($this->getAdapter());
        
        // begin the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->beginTransaction();
        }
        
        // force UTF-8 encoding for MySQL
        // TODO - this code will need to be abstracted when we support other db vendors
        //$this->getAdapter()->execute('SET NAMES UTF8');
        
        // Run the migration
        if (method_exists($migration, MigrationInterface::CHANGE)) {
            if ($direction == MigrationInterface::DOWN) {
                // Create an instance of the ProxyAdapter so we can record all
                // of the migration commands for reverse playback
                $proxyAdapter = new ProxyAdapter($this->getAdapter(), $this->getOutput());
                $migration->setAdapter($proxyAdapter);
                /** @noinspection PhpUndefinedMethodInspection */
                $migration->change();
                $proxyAdapter->executeInvertedCommands();
                $migration->setAdapter($this->getAdapter());
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $migration->change();
            }
        } else {
            $migration->{$direction}();
        }
        
        // commit the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->commitTransaction();
        }

        // Record it in the database
        $this->getAdapter()->migrated($migration, $direction, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', time()));
    }
    
    /**
     * Sets the environment's name.
     *
     * @param string $name Environment Name
     * @return Environment
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * Gets the environment name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Sets the environment's options.
     *
     * @param array $options Environment Options
     * @return Environment
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
    
    /**
     * Gets the environment's options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Sets the console output.
     *
     * @param OutputInterface $output Output
     * @return Environment
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        return $this;
    }
    
    /**
     * Gets the console output.
     *
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Gets all migrated version numbers.
     *
     * @return array
     */
    public function getVersions()
    {
        return $this->getAdapter()->getVersions();
    }
    
    /**
     * Sets the current version of the environment.
     *
     * @param int $version Environment Version
     * @return Environment
     */
    public function setCurrentVersion($version)
    {
        $this->currentVersion = $version;
        return $this;
    }
    
    /**
     * Gets the current version of the environment.
     *
     * @return int
     */
    public function getCurrentVersion()
    {
        // We don't cache this code as the current version is pretty volatile.
        // TODO - that means they're no point in a setter then?
        // maybe we should cache and call a reset() method everytime a migration is run
        $versions = $this->getVersions();
        $version = 0;
            
        if (!empty($versions)) {
            $version = end($versions);
        }
            
        $this->setCurrentVersion($version);
        return $this->currentVersion;
    }
    
    /**
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter Database Adapter
     * @return Environment
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
        if (isset($this->adapter)) {
            return $this->adapter;
        }
        if (!isset($this->options['adapter'])) {
            throw new \RuntimeException('No adapter was specified for environment: ' . $this->getName());
        }
        if (!isset($this->adapterFactories[$this->options['adapter']])) {
            throw new \RuntimeException('Invalid adapter specified: ' . $this->options['adapter']);
        }

        // Get the adapter factory, get the adapter, check the type, and return
        $adapterFactory = $this->adapterFactories[$this->options['adapter']];
        $adapter = $adapterFactory($this);
        if (!$adapter instanceof AdapterInterface) {
            throw new \RuntimeException('Adapter factory closure did not return an instance of \\Phinx\\Db\\Adapter\\AdapterInterface');
        }
        return $this->adapter = $adapter;
    }
    
    /**
     * Sets the schema table name.
     *
     * @param string $schemaTableName Schema Table Name
     * @return Environment
     */
    public function setSchemaTableName($schemaTableName)
    {
        $this->schemaTableName = $schemaTableName;
        return $this;
    }
    
    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName()
    {
        return $this->schemaTableName;
    }

    /**
     * @return string
     */
    public function schemaDump()
    {
        $dumper = new Dumper();
        $dumper->setAdapter($this->getAdapter());
        
        return $dumper->dump();
    }
    
    /**
     * @return callable[] Array of factory closures for Phinx's default adapter implementations.
     */
    public static final function defaultAdapterFactories()
    {
        return array(
            'mysql'     => function(Environment $env) {
                return new MysqlAdapter($env->getOptions(), $env->getOutput());
            },
            'pgsql'     => function(Environment $env) {
                return new PostgresAdapter($env->getOptions(), $env->getOutput());
            },
            'sqlite'    => function(Environment $env) {
                return new SQLiteAdapter($env->getOptions(), $env->getOutput());
            },
            'sqlsrv'    => function(Environment $env) {
                return new SqlServerAdapter($env->getOptions(), $env->getOutput());
            },
        );
    }
}
