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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\ForeignKey;
use Phinx\Migration\MigrationInterface;

/**
 * Table prefix/suffix adapter.
 *
 * Used for inserting a prefix or suffix into table names.
 *
 * @author Samuel Fisher <sam@sfisher.co>
 */
class TablePrefixAdapter implements AdapterInterface
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;
    
    /**
     * @var OutputInterface
     */
    protected $output;
        
    /**
     * @var array
     */
    protected $commands;
    
    /**
     * Class Constructor.
     *
     * @param array $options Options
     * @param AdapterInterface $adapter The adapter to proxy commands to
     * @return void
     */
    public function __construct(array $options, AdapterInterface $adapter = null)
    {
        $this->setOptions($options);
        if (null !== $adapter) {
            $this->setAdapter($adapter);
        }
    }
    
    /**
     * Sets the database adapter to proxy commands to.
     *
     * @param AdapterInterface $adapter Database Adapter
     * @return AdapterInterface
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
     * Sets the adapter options.
     *
     * @param array $options Options
     * @return AdapterInterface
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
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output)
    {
        $this->adapter->setOutput($output);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getOutput()
    {
        return $this->adapter->getOutput();
    }
    
    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $this->getAdapter()->connect();
    }
    
    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->getAdapter()->disconnect();
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute($sql)
    {
        return $this->getAdapter()->execute($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        return $this->getAdapter()->query($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fetchRow($sql)
    {
        return $this->getAdapter()->fetchRow($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fetchAll($sql)
    {
        return $this->getAdapter()->fetchAll($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getVersions()
    {
        return $this->getAdapter()->getVersions();
    }
    
    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        $this->getAdapter()->migrated($migration, $direction, $startTime, $endTime);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasSchemaTable()
    {
        return $this->getAdapter()->hasSchemaTable();
    }
    
    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {
        return $this->getAdapter()->createSchemaTable();
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapterType()
    {
        return 'ProxyAdapter';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return $this->getAdapter()->getColumnTypes();
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasTransactions()
    {
        return $this->getAdapter()->hasTransactions();
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        return $this->getAdapter()->beginTransaction();
    }
    
    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        return $this->getAdapter()->commitTransaction();
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        return $this->getAdapter()->rollbackTransaction();
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return $this->getAdapter()->quoteTableName($tableName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return $this->getAdapter()->quoteColumnName($columnName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        return $this->getAdapter()->hasTable($adapterTableName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table)
    {
        $adapterTable = clone $table;
        $adapterTableName = $this->getAdapterTableName($table->getName());
        $adapterTable->setName($adapterTableName);
        $this->getAdapter()->createTable($adapterTable);
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $adapterNewTableName = $this->getAdapterTableName($newTableName);
        $this->getAdapter()->renameTable($adapterTableName, $adapterNewTableName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->dropTable($adapterTableName);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        return $this->getAdapter()->getColumns($adapterTableName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        return $this->getAdapter()->hasColumn($adapterTableName, $columnName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $adapterTable = clone $table;
        $adapterTableName = $this->getAdapterTableName($table->getName());
        $adapterTable->setName($adapterTableName);
        $this->getAdapter()->addColumn($adapterTable, $column);
    }
    
    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->renameColumn($adapterTableName, $columnName, $newColumnName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->changeColumn($adapterTableName, $columnName, $newColumn);
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->dropColumn($adapterTableName, $columnName);
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        return $this->getAdapter()->hasIndex($adapterTableName, $columns);
    }
    
    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        $adapterTable = clone $table;
        $adapterTableName = $this->getAdapterTableName($table->getName());
        $adapterTable->setName($adapterTableName);
        $this->getAdapter()->addIndex($adapterTable, $index);
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns, $options = array())
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->dropIndex($adapterTableName, $columns, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->dropIndexByName($adapterTableName, $indexName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        return $this->getAdapter()->hasForeignKey($adapterTableName, $columns, $constraint);
    }
    
    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $adapterTable = clone $table;
        $adapterTableName = $this->getAdapterTableName($table->getName());
        $adapterTable->setName($adapterTableName);
        $this->getAdapter()->addForeignKey($adapterTable, $foreignKey);
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        $adapterTableName = $this->getAdapterTableName($tableName);
        $this->getAdapter()->dropForeignKey($adapterTableName, $columns, $constraint);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSqlType($type)
    {
        return $this->getAdapter()->getSqlType($type);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        $this->getAdapter()->createDatabase($name, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        return $this->getAdapter()->hasDatabase($name);
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        return $this->getAdapter()->dropDatabase($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection() {
        return $this->getAdapter()->getConnection();
    }
    
    /**
     * Gets the table prefix.
     * 
     * @return string
     */
    public function getPrefix() {
        return isset($this->options['table_prefix'])
            ? $this->options['table_prefix']
            : '';
    }
    
    /**
     * Sets the table prefix.
     * 
     * @param string $prefix
     */
    public function setPrefix($prefix) {
        $this->options['table_prefix'] = $prefix;
    }
    
    /**
     * Gets the table suffix.
     * 
     * @return string
     */
    public function getSuffix() {
        return isset($this->options['table_suffix'])
            ? $this->options['table_suffix']
            : '';
    }
    
    /**
     * Sets the table suffix.
     * 
     * @param string $suffix
     */
    public function setSuffix($suffix) {
        $this->options['table_suffix'] = $suffix;
    }
    
    /**
     * Applies the prefix and suffix to the table name.
     * 
     * @param string $tableName
     * @return string
     */
    public function getAdapterTableName($tableName) {
        return $this->getPrefix() . $tableName . $this->getSuffix();
    }
}
