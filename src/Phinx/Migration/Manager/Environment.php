<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2012 Rob Morgan
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

use Phinx\Db\Adapter\AdapterInterface,
    Phinx\Db\Adapter\PdoAdapter,
    Phinx\Db\Adapter\MysqlAdapter,
    Phinx\Db\Adapter\ProxyAdapter,
    Phinx\Migration\MigrationInterface;

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
     * @var int
     */
    protected $currentVersion;
    
    /**
     * @var string
     */
    protected $schemaTableName = 'phinxlog';
    
    /**
     * @var AdapterInterface
     */
    protected $adapter;
    
    /**
     * Class Constructor.
     *
     * @param string $name Environment Name
     * @param array $options Options
     * @return void
     */
    public function __construct($name, $options)
    {
        $this->name = $name;
        $this->options = $options;
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
        
        // Run the migration
        if (method_exists($migration, MigrationInterface::CHANGE)) {
            if ($direction == MigrationInterface::DOWN) {
                // Create an instance of the ProxyAdapter so we can record all
                // of the migration commands for reverse playback
                $proxyAdapter = new ProxyAdapter($this->getAdapter());
                $migration->setAdapter($proxyAdapter);
                $migration->change();
                $proxyAdapter->executeInvertedCommands();
                $migration->setAdapter($this->getAdapter());
            } else {
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
        if (null === $this->adapter) {
            if (isset($this->options['adapter'])) {
                // Adapter Factory
                switch (strtolower($this->options['adapter'])) {
                    case 'mysql':
                        $this->setAdapter(new MysqlAdapter($this->options));
                        break;
                    default:
                        throw new \RuntimeException('Invalid adapter specified: ' . $this->options['adapter']);
                }
            } else {
                throw new \RuntimeException('No adapter was specified for environment: ' . $this->getName());
            }
        }
        
        return $this->adapter;
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
}