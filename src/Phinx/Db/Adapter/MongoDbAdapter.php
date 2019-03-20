<?php

namespace Phinx\Db\Adapter;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Adapter\AdapterInterface as PhinxAdapter;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MongoMigrationAdapter implements PhinxAdapter
{

    protected $collectionName = 'phinx_migration';

    /** @var InputInterface $consoleInput */
    protected $consoleInput;

    /** @var OutputInterface $consoleOutput */
    protected $consoleOutput;

    /** @var Client $mongoClient */
    protected $mongoClient;

    /** @var Database $database */
    protected $database;

    /** @var Collection $collection */
    protected $collection;

    /** @var string $databaseName */
    protected $databaseName;

    /** @var string $uri */
    protected $uri;

    protected $session;

    protected $options = [
        'table_prefix' => ''
    ];

    function __construct(array $options)
    {
        $this->collectionName = $options['default_migration_table'];
        $this->databaseName = $options['name'];
        $this->uri = $options['uri'];
        $this->options = $options;
        $this->connect();
    }

    /**
     * @return array
     */
    public function getVersions()
    {
        return array_keys($this->getVersionLog());
    }

    /**
     * @return array
     */
    public function getVersionLog()
    {
        $result = [];
        $rows = $this->getMigrationCollection()->find();
        foreach ($rows as $row) {
            $result[$row['version']] = $row;
        }
        return $result;
    }

    /**
     * @param array $options
     * @return $this|PhinxAdapter
     */
    public function setOptions(array $options)
    {
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    public function hasOption($name)
    {
        return true;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getOption($name)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }
        return null;
    }

    /**
     * @param InputInterface $input
     * @return $this|PhinxAdapter
     */
    public function setInput(InputInterface $input)
    {
        $this->consoleInput = $input;
        return $this;
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->consoleInput;
    }

    /**
     * @param OutputInterface $output
     * @return PhinxAdapter
     */
    public function setOutput(OutputInterface $output)
    {
        $this->consoleOutput = $output;
        return $this;
    }

    public function getOutput()
    {
        return $this->consoleOutput;
    }

    /**
     * @param MigrationInterface $migration
     * @param string $direction
     * @param int $startTime
     * @param int $endTime
     * @return $this|PhinxAdapter
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            $this->getMigrationCollection()->insertOne([
                'name' => $migration->getName(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'version' => $migration->getVersion(),
                'breakpoint' => false
            ]);
        } else {
            $this->getMigrationCollection()->deleteOne([
                'version' => $migration->getVersion()
            ]);
        }
        return $this;
    }

    /**
     * @param MigrationInterface $migration
     * @return $this|PhinxAdapter
     */
    public function toggleBreakpoint(MigrationInterface $migration)
    {
        return $this;
    }

    public function resetAllBreakpoints()
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    /**
     * @return bool
     */
    public function hasSchemaTable()
    {
        return true;
    }

    public function createSchemaTable()
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function getAdapterType()
    {
        return null;
    }

    public function connect()
    {
        $this->mongoClient = new Client($this->uri);
        $this->database = $this->mongoClient->selectDatabase($this->databaseName);
        $this->collection = $this->database->selectCollection($this->collectionName);
    }

    public function disconnect()
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function hasTransactions()
    {
        return false;
    }

    public function beginTransaction()
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function commitTransaction()
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function rollbackTransaction()
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function execute($sql)
    {
        throw new \InvalidArgumentException("Don't know how to execute");
    }

    public function executeActions(Table $table, array $actions)
    {
        foreach ($actions as $action) {

            if ($action instanceof AddIndex) {
                $columns = $action->getIndex()->getColumns();
                $options = [];
                $indexName = $action->getIndex()->getName();
                if (!empty($indexName)) {
                    $options['name'] = $indexName;
                }
                $type = $action->getIndex()->getType();
                if ($type == 'unique') {
                    $options['unique'] = true;
                }
                $this->getDatabase()
                    ->selectCollection($action->getTable()->getName())
                    ->createIndex($columns, $options);
            } elseif ($action instanceof DropIndex) {
                $indexName = $action->getIndex()->getName();
                if (!empty($indexName)) {
                    $this->getDatabase()->selectCollection($action->getTable()->getName())
                        ->dropIndex($indexName);
                }
            } elseif ($action instanceof DropTable) {
                $this->getDatabase()
                    ->dropCollection($action->getTable()->getName());
            } else {
                throw new \InvalidArgumentException(
                    sprintf("Don't know how to execute action: '%s'", get_class($action))
                );
            }
        }
    }

    public function getQueryBuilder()
    {
        return null;
    }

    public function query($sql)
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function fetchRow($sql)
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    public function fetchAll($sql)
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    /**
     * @param Table $table
     * @param array $row
     */
    public function insert(Table $table, $row)
    {
        $this->getDatabase()->selectCollection($table->getName())->insertOne($row);
    }

    /**
     * @param Table $table
     * @param array $rows
     */
    public function bulkinsert(Table $table, $rows)
    {
        $this->getDatabase()->selectCollection($table->getName())->insertMany($rows);
    }

    /**
     * @param string $tableName
     * @return mixed|string
     */
    public function quoteTableName($tableName)
    {
        return str_replace('.', '`.`', $this->quoteColumnName($tableName));
    }

    /**
     * @param string $columnName
     * @return string
     */
    public function quoteColumnName($columnName)
    {
        return '`' . str_replace('`', '``', $columnName) . '`';
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public function hasTable($tableName)
    {
        return true;
    }

    /**
     * @param Table $table
     * @param array $columns
     * @param array $indexes
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
    }

    /**
     * @param string $tableName
     */
    public function truncateTable($tableName)
    {
        $this->getDatabase()->dropCollection($tableName);
    }

    /**
     * @param string $tableName
     * @return array|Column[]
     */
    public function getColumns($tableName)
    {
        return [];
    }

    /**
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    public function hasColumn($tableName, $columnName)
    {
        return true;
    }

    /**
     * @param string $tableName
     * @param mixed $columns
     * @return bool|void
     */
    public function hasIndex($tableName, $columns)
    {
        $indexes = $this->getDatabase()->selectCollection($tableName)->listIndexes();
        $this->hasValues($indexes, $columns);
    }

    /**
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    public function hasIndexByName($tableName, $indexName)
    {
        $indexes = $this->getDatabase()->selectCollection($tableName)->listIndexes();
        return $this->hasValue($indexes, $indexName);
    }

    /**
     * @param string $tableName
     * @param string[] $columns
     * @param null $constraint
     * @return bool
     */
    public function hasPrimaryKey($tableName, $columns, $constraint = null)
    {
        return true;
    }

    /**
     * @param string $tableName
     * @param string[] $columns
     * @param null $constraint
     * @return bool
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        return false;
    }

    /**
     * @return array
     */
    public function getColumnTypes()
    {
        return [
            self::PHINX_TYPE_BIG_INTEGER,
            self::PHINX_TYPE_BOOLEAN,
            self::PHINX_TYPE_INTEGER,
            self::PHINX_TYPE_STRING,
            self::PHINX_TYPE_FLOAT
        ];
    }

    /**
     * @param Column $column
     * @return bool
     */
    public function isValidColumnType(Column $column)
    {
        return true;
    }

    /**
     * @param string $type
     * @param null $limit
     * @return array|string[]
     */
    public function getSqlType($type, $limit = null)
    {
        return [];
    }

    /**
     * @param string $name
     * @param array $options
     */
    public function createDatabase($name, $options = [])
    {
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasDatabase($name)
    {
        return true;
    }

    /**
     * @param string $name
     */
    public function dropDatabase($name)
    {
        $this->mongoClient->dropDatabase($name);
    }

    public function createSchema($schemaName = 'public')
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    /**
     * @param string $schemaName
     */
    public function dropSchema($schemaName)
    {
        $this->getDatabase()->dropCollection($schemaName);
    }

    public function castToBool($value)
    {
        throw new \InvalidArgumentException("Not implemented");
    }

    protected function getMigrationCollection()
    {
        return $this->collection;
    }

    /**
     * @return Database
     */
    protected function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param \Iterator $iterator
     * @param $value
     * @return bool
     */
    protected function hasValue(\Iterator $iterator, $value)
    {
        foreach ($iterator as $iteration) {
            if ($iteration == $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \Iterator $iterator
     * @param $values
     * @return bool
     */
    protected function hasValues(\Iterator $iterator, $values)
    {
        foreach ($iterator as $iteration) {
            if ($this->hasValue($iteration, $values)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Client|\PDO
     */
    public function getConnection()
    {
        return $this->mongoClient;
    }
}