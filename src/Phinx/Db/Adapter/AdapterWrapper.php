<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Adapter;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adapter Wrapper.
 *
 * Proxy commands through to another adapter, allowing modification of
 * parameters during calls.
 *
 * @author Woody Gilk <woody.gilk@gmail.com>
 */
abstract class AdapterWrapper implements AdapterInterface, WrapperInterface
{
    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * @inheritDoc
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * @inheritDoc
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options)
    {
        $this->adapter->setOptions($options);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOptions()
    {
        return $this->adapter->getOptions();
    }

    /**
     * @inheritDoc
     */
    public function hasOption($name)
    {
        return $this->adapter->hasOption($name);
    }

    /**
     * @inheritDoc
     */
    public function getOption($name)
    {
        return $this->adapter->getOption($name);
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input)
    {
        $this->adapter->setInput($input);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInput()
    {
        return $this->adapter->getInput();
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output)
    {
        $this->adapter->setOutput($output);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        return $this->adapter->getOutput();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function connect()
    {
        $this->getAdapter()->connect();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function disconnect()
    {
        $this->getAdapter()->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function execute($sql)
    {
        return $this->getAdapter()->execute($sql);
    }

    /**
     * @inheritDoc
     */
    public function query($sql)
    {
        return $this->getAdapter()->query($sql);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function insert(Table $table, $row)
    {
        $this->getAdapter()->insert($table, $row);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function bulkinsert(Table $table, $rows)
    {
        $this->getAdapter()->bulkinsert($table, $rows);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow($sql)
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * @inheritDoc
     */
    public function fetchAll($sql)
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * @inheritDoc
     */
    public function getVersions()
    {
        return $this->getAdapter()->getVersions();
    }

    /**
     * @inheritDoc
     */
    public function getVersionLog()
    {
        return $this->getAdapter()->getVersionLog();
    }

    /**
     * @inheritDoc
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        $this->getAdapter()->migrated($migration, $direction, $startTime, $endTime);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toggleBreakpoint(MigrationInterface $migration)
    {
        $this->getAdapter()->toggleBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resetAllBreakpoints()
    {
        return $this->getAdapter()->resetAllBreakpoints();
    }

    /**
     * @inheritDoc
     */
    public function setBreakpoint(MigrationInterface $migration)
    {
        $this->getAdapter()->setBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unsetBreakpoint(MigrationInterface $migration)
    {
        $this->getAdapter()->unsetBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasSchemaTable()
    {
        return $this->getAdapter()->hasSchemaTable();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function createSchemaTable()
    {
        $this->getAdapter()->createSchemaTable();
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes()
    {
        return $this->getAdapter()->getColumnTypes();
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(Column $column)
    {
        return $this->getAdapter()->isValidColumnType($column);
    }

    /**
     * @inheritDoc
     */
    public function hasTransactions()
    {
        return $this->getAdapter()->hasTransactions();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->getAdapter()->beginTransaction();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function commitTransaction()
    {
        $this->getAdapter()->commitTransaction();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function rollbackTransaction()
    {
        $this->getAdapter()->rollbackTransaction();
    }

    /**
     * @inheritDoc
     */
    public function quoteTableName($tableName)
    {
        return $this->getAdapter()->quoteTableName($tableName);
    }

    /**
     * @inheritDoc
     */
    public function quoteColumnName($columnName)
    {
        return $this->getAdapter()->quoteColumnName($columnName);
    }

    /**
     * @inheritDoc
     */
    public function hasTable($tableName)
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $this->getAdapter()->createTable($table, $columns, $indexes);
    }

    /**
     * @inheritDoc
     */
    public function getColumns($tableName)
    {
        return $this->getAdapter()->getColumns($tableName);
    }

    /**
     * @inheritDoc
     */
    public function hasColumn($tableName, $columnName)
    {
        return $this->getAdapter()->hasColumn($tableName, $columnName);
    }

    /**
     * @inheritDoc
     */
    public function hasIndex($tableName, $columns)
    {
        return $this->getAdapter()->hasIndex($tableName, $columns);
    }

    /**
     * @inheritDoc
     */
    public function hasIndexByName($tableName, $indexName)
    {
        return $this->getAdapter()->hasIndexByName($tableName, $indexName);
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey($tableName, $columns, $constraint = null)
    {
        return $this->getAdapter()->hasPrimaryKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        return $this->getAdapter()->hasForeignKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function getSqlType($type, $limit = null)
    {
        return $this->getAdapter()->getSqlType($type, $limit);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function createDatabase($name, $options = [])
    {
        $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase($name)
    {
        return $this->getAdapter()->hasDatabase($name);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropDatabase($name)
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function createSchema($schemaName = 'public')
    {
        $this->getAdapter()->createSchema($schemaName);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropSchema($schemaName)
    {
        $this->getAdapter()->dropSchema($schemaName);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function truncateTable($tableName)
    {
        $this->getAdapter()->truncateTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value)
    {
        return $this->getAdapter()->castToBool($value);
    }

    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        return $this->getAdapter()->getConnection();
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function executeActions(Table $table, array $actions)
    {
        $this->getAdapter()->executeActions($table, $actions);
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder()
    {
        return $this->getAdapter()->getQueryBuilder();
    }
}
