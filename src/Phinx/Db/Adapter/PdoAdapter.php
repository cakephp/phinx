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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Db\Table,
    Phinx\Migration\MigrationInterface;

/**
 * Phinx PDO Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class PdoAdapter implements AdapterInterface
{
    /**
     * @var array
     */
    protected $options;
    
    /**
     * @var string
     */
    protected $schemaTableName = 'phinxlog';

    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * Class Constructor.
     *
     * @param array $options Options
     * @return void
     */
    public function __construct(array $options)
    {
        $this->setOptions($options);
    }
    
    /**
     * Sets the adapter options.
     *
     * @param array $options Options
     * return AdapterInterface
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }
    
    /**
     * Gets the adapter options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
    
    /**
     * Sets the schema table name.
     *
     * @param string $schemaTableName Schema Table Name
     * @return void
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
     * Sets the database connection.
     *
     * @param \PDO $connection Connection
     * @return AdapterInterface
     */
    public function setConnection(\PDO $connection)
    {
        $this->connection = $connection;
        return $this;
    }
    
    /**
     * Gets the database connection
     *
     * @return \PDO
     */
    public function getConnection()
    {
        if (null === $this->connection) {
            $this->connect();
        }
        return $this->connection;
    }
     
    /**
     * {@inheritdoc}
     */
    public function connect()
    {
    }
     
    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
    }
     
    /**
     * {@inheritdoc}
     */
    public function execute($sql)
    {
        return $this->getConnection()->exec($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        return $this->getConnection()->query($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fetchRow($sql)
    {
        $result = $this->query($sql);
        return $result->fetch();
    }
    
    /**
     * {@inheritdoc}
     */
    public function fetchAll($sql)
    {
        $rows = array();
        $result = $this->query($sql);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getVersions()
    {
        $versions = array();
        
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY version ASC', $this->getSchemaTableName()));
        return array_map(function($v) {return $v['version'];}, $rows);
    }
    
    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strtolower($direction) == 'up') {
            // up
            $sql = sprintf(
                'INSERT INTO %s ('
                . 'version, start_time, end_time'
                . ') VALUES ('
                . '"%s",'
                . '"%s",'
                . '"%s"'
                . ');',
                $this->getSchemaTableName(),
                $migration->getVersion(),
                $startTime,
                $endTime
            );

            $this->query($sql);
        } else {
            // down
            $sql = sprintf(
                "DELETE FROM %s WHERE version = '%s'",
                $this->getSchemaTableName(),
                $migration->getVersion()
            );
            
            $this->query($sql);
        }

        return $this;
    }
    
    /**
     * Describes a database table.
     *
     * @todo MySQL Specific so move to MysqlAdapter.
     * @return array
     */
    public function describeTable($tableName)
    {
        $options = $this->getOptions();
        
        // mysql specific
        $sql = sprintf(
            'SELECT *'
            . ' FROM information_schema.tables'
            . ' WHERE table_schema = "%s"'
            . ' AND table_name = "%s"',
            $options['name'],
            $tableName
        );
        
        return $this->fetchRow($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasSchemaTable()
    {
        return $this->hasTable($this->getSchemaTableName());
    }
    
    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {
        try {
            $options = array(
                'id' => false
            );
            
            $table = new \Phinx\Db\Table($this->getSchemaTableName(), $options, $this);
            $table->addColumn('version', 'biginteger', array('limit' => 14))
                  ->addColumn('start_time', 'timestamp')
                  ->addColumn('end_time', 'timestamp')
                  ->save();
        } catch(\Exception $exception) {
            throw new \InvalidArgumentException('There was a problem creating the schema table');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapterType()
    {
        $options = $this->getOptions();
        return $options['adapter'];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array(
            'primary_key',
            'string',
            'text',
            'integer',
            'biginteger',
            'float',
            'decimal',
            'datetime',
            'timestamp',
            'time',
            'date',
            'binary',
            'boolean'
        );
    }
}