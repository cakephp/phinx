<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Adapter;

use BadMethodCallException;
use InvalidArgumentException;
use PDO;
use PDOException;
use Phinx\Config\Config;
use Phinx\Db\Action\AddColumn;
use Phinx\Db\Action\AddForeignKey;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\ChangeColumn;
use Phinx\Db\Action\ChangeComment;
use Phinx\Db\Action\ChangePrimaryKey;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Table as DbTable;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Migration\MigrationInterface;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Phinx PDO Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class PdoAdapter extends AbstractAdapter implements DirectActionInterface
{
    /**
     * @var \PDO|null
     */
    protected $connection;

    /**
     * Writes a message to stdout if verbose output is on
     *
     * @param string $message The message to show
     *
     * @return void
     */
    protected function verboseLog($message)
    {
        if (!$this->isDryRunEnabled() &&
             $this->getOutput()->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE
        ) {
            return;
        }

        $this->getOutput()->writeln($message);
    }

    /**
     * Create PDO connection
     *
     * @param string $dsn Connection string
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param array $options Connection options
     * @return \PDO
     */
    protected function createPdoConnection($dsn, $username = null, $password = null, array $options = [])
    {
        try {
            $db = new PDO($dsn, $username, $password, $options);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new InvalidArgumentException(sprintf(
                'There was a problem connecting to the database: %s',
                $e->getMessage()
            ), $e->getCode(), $e);
        }

        return $db;
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options)
    {
        parent::setOptions($options);

        if (isset($options['connection'])) {
            $this->setConnection($options['connection']);
        }

        return $this;
    }

    /**
     * Sets the database connection.
     *
     * @param \PDO $connection Connection
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function setConnection(PDO $connection)
    {
        $this->connection = $connection;

        // Create the schema table if it doesn't already exist
        if (!$this->hasSchemaTable()) {
            $this->createSchemaTable();
        } else {
            $table = new DbTable($this->getSchemaTableName(), [], $this);
            if (!$table->hasColumn('migration_name')) {
                $table
                    ->addColumn(
                        'migration_name',
                        'string',
                        ['limit' => 100, 'after' => 'version', 'default' => null, 'null' => true]
                    )
                    ->save();
            }
            if (!$table->hasColumn('breakpoint')) {
                $table
                    ->addColumn('breakpoint', 'boolean', ['default' => false])
                    ->save();
            }
        }

        return $this;
    }

    /**
     * Gets the database connection
     *
     * @return \PDO
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function connect()
    {
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function disconnect()
    {
    }

    /**
     * @inheritDoc
     */
    public function execute($sql)
    {
        $sql = rtrim($sql, "; \t\n\r\0\x0B") . ';';
        $this->verboseLog($sql);

        if ($this->isDryRunEnabled()) {
            return 0;
        }

        return $this->getConnection()->exec($sql);
    }

    /**
     * Returns the Cake\Database connection object using the same underlying
     * PDO object as this connection.
     *
     * @return \Cake\Database\Connection
     */
    abstract public function getDecoratedConnection();

    /**
     * @inheritDoc
     */
    public function getQueryBuilder()
    {
        return $this->getDecoratedConnection()->newQuery();
    }

    /**
     * Executes a query and returns PDOStatement.
     *
     * @param string $sql SQL
     *
     * @return \PDOStatement
     */
    public function query($sql)
    {
        return $this->getConnection()->query($sql);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow($sql)
    {
        $result = $this->query($sql);

        return $result->fetch();
    }

    /**
     * @inheritDoc
     */
    public function fetchAll($sql)
    {
        $rows = [];
        $result = $this->query($sql);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function insert(Table $table, $row)
    {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteTableName($table->getName())
        );
        $columns = array_keys($row);
        $sql .= '(' . implode(', ', array_map([$this, 'quoteColumnName'], $columns)) . ')';

        foreach ($row as $column => $value) {
            if (is_bool($value)) {
                $row[$column] = $this->castToBool($value);
            }
        }

        if ($this->isDryRunEnabled()) {
            $sql .= ' VALUES (' . implode(', ', array_map([$this, 'quoteValue'], $row)) . ');';
            $this->output->writeln($sql);
        } else {
            $sql .= ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($row));
        }
    }

    /**
     * Quotes a database value.
     *
     * @param mixed $value The value to quote
     *
     * @return mixed
     */
    private function quoteValue($value)
    {
        if (is_numeric($value)) {
            return $value;
        }

        if ($value === null) {
            return 'null';
        }

        return $this->getConnection()->quote($value);
    }

    /**
     * Quotes a database string.
     *
     * @param string $value The string to quote
     *
     * @return string
     */
    protected function quoteString($value)
    {
        return $this->getConnection()->quote($value);
    }

    /**
     * @inheritDoc
     */
    public function bulkinsert(Table $table, $rows)
    {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteTableName($table->getName())
        );
        $current = current($rows);
        $keys = array_keys($current);
        $sql .= '(' . implode(', ', array_map([$this, 'quoteColumnName'], $keys)) . ') VALUES ';

        if ($this->isDryRunEnabled()) {
            $values = array_map(function ($row) {
                return '(' . implode(', ', array_map([$this, 'quoteValue'], $row)) . ')';
            }, $rows);
            $sql .= implode(', ', $values) . ';';
            $this->output->writeln($sql);
        } else {
            $count_keys = count($keys);
            $query = '(' . implode(', ', array_fill(0, $count_keys, '?')) . ')';
            $count_vars = count($rows);
            $queries = array_fill(0, $count_vars, $query);
            $sql .= implode(',', $queries);
            $stmt = $this->getConnection()->prepare($sql);
            $vals = [];

            foreach ($rows as $row) {
                foreach ($row as $v) {
                    if (is_bool($v)) {
                        $vals[] = $this->castToBool($v);
                    } else {
                        $vals[] = $v;
                    }
                }
            }

            $stmt->execute($vals);
        }
    }

    /**
     * @inheritDoc
     */
    public function getVersions()
    {
        $rows = $this->getVersionLog();

        return array_keys($rows);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     */
    public function getVersionLog()
    {
        $result = [];

        switch ($this->options['version_order']) {
            case Config::VERSION_ORDER_CREATION_TIME:
                $orderBy = 'version ASC';
                break;
            case Config::VERSION_ORDER_EXECUTION_TIME:
                $orderBy = 'start_time ASC, version ASC';
                break;
            default:
                throw new RuntimeException('Invalid version_order configuration option');
        }

        // This will throw an exception if doing a --dry-run without any migrations as phinxlog
        // does not exist, so in that case, we can just expect to trivially return empty set
        try {
            $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->quoteTableName($this->getSchemaTableName()), $orderBy));
        } catch (PDOException $e) {
            if (!$this->isDryRunEnabled()) {
                throw $e;
            }
            $rows = [];
        }

        foreach ($rows as $version) {
            $result[$version['version']] = $version;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                "INSERT INTO %s (%s, %s, %s, %s, %s) VALUES ('%s', '%s', '%s', '%s', %s);",
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('version'),
                $this->quoteColumnName('migration_name'),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('end_time'),
                $this->quoteColumnName('breakpoint'),
                $migration->getVersion(),
                substr($migration->getName(), 0, 100),
                $startTime,
                $endTime,
                $this->castToBool(false)
            );

            $this->execute($sql);
        } else {
            // down
            $sql = sprintf(
                "DELETE FROM %s WHERE %s = '%s'",
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('version'),
                $migration->getVersion()
            );

            $this->execute($sql);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toggleBreakpoint(MigrationInterface $migration)
    {
        $this->query(
            sprintf(
                'UPDATE %1$s SET %2$s = CASE %2$s WHEN %3$s THEN %4$s ELSE %3$s END, %7$s = %7$s WHERE %5$s = \'%6$s\';',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('breakpoint'),
                $this->castToBool(true),
                $this->castToBool(false),
                $this->quoteColumnName('version'),
                $migration->getVersion(),
                $this->quoteColumnName('start_time')
            )
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resetAllBreakpoints()
    {
        return $this->execute(
            sprintf(
                'UPDATE %1$s SET %2$s = %3$s, %4$s = %4$s WHERE %2$s <> %3$s;',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('breakpoint'),
                $this->castToBool(false),
                $this->quoteColumnName('start_time')
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function setBreakpoint(MigrationInterface $migration)
    {
        return $this->markBreakpoint($migration, true);
    }

    /**
     * @inheritDoc
     */
    public function unsetBreakpoint(MigrationInterface $migration)
    {
        return $this->markBreakpoint($migration, false);
    }

    /**
     * Mark a migration breakpoint.
     *
     * @param \Phinx\Migration\MigrationInterface $migration The migration target for the breakpoint
     * @param bool $state The required state of the breakpoint
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    protected function markBreakpoint(MigrationInterface $migration, $state)
    {
        $this->query(
            sprintf(
                'UPDATE %1$s SET %2$s = %3$s, %4$s = %4$s WHERE %5$s = \'%6$s\';',
                $this->quoteTableName($this->getSchemaTableName()),
                $this->quoteColumnName('breakpoint'),
                $this->castToBool($state),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('version'),
                $migration->getVersion()
            )
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     *
     * @return void
     */
    public function createSchema($schemaName = 'public')
    {
        throw new BadMethodCallException('Creating a schema is not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     *
     * @return void
     */
    public function dropSchema($name)
    {
        throw new BadMethodCallException('Dropping a schema is not supported');
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes()
    {
        return [
            'string',
            'char',
            'text',
            'smallinteger',
            'integer',
            'biginteger',
            'bit',
            'float',
            'decimal',
            'double',
            'datetime',
            'timestamp',
            'time',
            'date',
            'blob',
            'binary',
            'varbinary',
            'boolean',
            'uuid',
            // Geospatial data types
            'geometry',
            'point',
            'linestring',
            'polygon',
        ];
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value)
    {
        return (bool)$value ? 1 : 0;
    }

    /**
     * Retrieve a database connection attribute
     *
     * @see http://php.net/manual/en/pdo.getattribute.php
     *
     * @param int $attribute One of the PDO::ATTR_* constants
     *
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        return $this->connection->getAttribute($attribute);
    }

    /**
     * Get the definition for a `DEFAULT` statement.
     *
     * @param mixed $default Default value
     * @param string|null $columnType column type added
     *
     * @return string
     */
    protected function getDefaultValueDefinition($default, $columnType = null)
    {
        if (is_string($default) && $default !== 'CURRENT_TIMESTAMP') {
            $default = $this->getConnection()->quote($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        } elseif ($default !== null && $columnType === static::PHINX_TYPE_BOOLEAN) {
            $default = $this->castToBool((bool)$default);
        }

        return isset($default) ? " DEFAULT $default" : '';
    }

    /**
     * Executes all the ALTER TABLE instructions passed for the given table
     *
     * @param string $tableName The table name to use in the ALTER statement
     * @param \Phinx\Db\Util\AlterInstructions $instructions The object containing the alter sequence
     *
     * @return void
     */
    protected function executeAlterSteps($tableName, AlterInstructions $instructions)
    {
        $alter = sprintf('ALTER TABLE %s %%s', $this->quoteTableName($tableName));
        $instructions->execute($alter, [$this, 'execute']);
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function addColumn(Table $table, Column $column)
    {
        $instructions = $this->getAddColumnInstructions($table, $column);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified column to a database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Column $column Column
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getAddColumnInstructions(Table $table, Column $column);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $instructions = $this->getRenameColumnInstructions($tableName, $columnName, $newColumnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     *
     * @return AlterInstructions:w
     */
    abstract protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $instructions = $this->getChangeColumnInstructions($tableName, $columnName, $newColumn);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to change a table column type.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param \Phinx\Db\Table\Column $newColumn New Column
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropColumn($tableName, $columnName)
    {
        $instructions = $this->getDropColumnInstructions($tableName, $columnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getDropColumnInstructions($tableName, $columnName);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function addIndex(Table $table, Index $index)
    {
        $instructions = $this->getAddIndexInstructions($table, $index);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified index to a database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Index $index Index
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getAddIndexInstructions(Table $table, Index $index);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropIndex($tableName, $columns)
    {
        $instructions = $this->getDropIndexByColumnsInstructions($tableName, $columns);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified index from a database table.
     *
     * @param string $tableName The name of of the table where the index is
     * @param mixed $columns Column(s)
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getDropIndexByColumnsInstructions($tableName, $columns);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $instructions = $this->getDropIndexByNameInstructions($tableName, $indexName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the index specified by name from a database table.
     *
     * @param string $tableName The table name whe the index is
     * @param string $indexName The name of the index
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getDropIndexByNameInstructions($tableName, $indexName);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $instructions = $this->getAddForeignKeyInstructions($table, $foreignKey);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to adds the specified foreign key to a database table.
     *
     * @param \Phinx\Db\Table\Table $table The table to add the constraint to
     * @param \Phinx\Db\Table\ForeignKey $foreignKey The foreign key to add
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        if ($constraint) {
            $instructions = $this->getDropForeignKeyInstructions($tableName, $constraint);
        } else {
            $instructions = $this->getDropForeignKeyByColumnsInstructions($tableName, $columns);
        }

        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param string $constraint Constraint name
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getDropForeignKeyInstructions($tableName, $constraint);

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param array $columns The list of column names
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getDropForeignKeyByColumnsInstructions($tableName, $columns);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function dropTable($tableName)
    {
        $instructions = $this->getDropTableInstructions($tableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified database table.
     *
     * @param string $tableName Table Name
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getDropTableInstructions($tableName);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function renameTable($tableName, $newTableName)
    {
        $instructions = $this->getRenameTableInstructions($tableName, $newTableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified database table.
     *
     * @param string $tableName Table Name
     * @param string $newTableName New Name
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getRenameTableInstructions($tableName, $newTableName);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function changePrimaryKey(Table $table, $newColumns)
    {
        $instructions = $this->getChangePrimaryKeyInstructions($table, $newColumns);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to change the primary key for the specified database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param string|array|null $newColumns Column name(s) to belong to the primary key, or null to drop the key
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getChangePrimaryKeyInstructions(Table $table, $newColumns);

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function changeComment(Table $table, $newComment)
    {
        $instructions = $this->getChangeCommentInstructions($table, $newComment);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instruction to change the comment for the specified database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param string|null $newComment New comment string, or null to drop the comment
     *
     * @return \Phinx\Db\Util\AlterInstructions
     */
    abstract protected function getChangeCommentInstructions(Table $table, $newComment);

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function executeActions(Table $table, array $actions)
    {
        $instructions = new AlterInstructions();

        foreach ($actions as $action) {
            switch (true) {
                case ($action instanceof AddColumn):
                    $instructions->merge($this->getAddColumnInstructions($table, $action->getColumn()));
                    break;

                case ($action instanceof AddIndex):
                    $instructions->merge($this->getAddIndexInstructions($table, $action->getIndex()));
                    break;

                case ($action instanceof AddForeignKey):
                    $instructions->merge($this->getAddForeignKeyInstructions($table, $action->getForeignKey()));
                    break;

                case ($action instanceof ChangeColumn):
                    $instructions->merge($this->getChangeColumnInstructions(
                        $table->getName(),
                        $action->getColumnName(),
                        $action->getColumn()
                    ));
                    break;

                case ($action instanceof DropForeignKey && !$action->getForeignKey()->getConstraint()):
                    $instructions->merge($this->getDropForeignKeyByColumnsInstructions(
                        $table->getName(),
                        $action->getForeignKey()->getColumns()
                    ));
                    break;

                case ($action instanceof DropForeignKey && $action->getForeignKey()->getConstraint()):
                    $instructions->merge($this->getDropForeignKeyInstructions(
                        $table->getName(),
                        $action->getForeignKey()->getConstraint()
                    ));
                    break;

                case ($action instanceof DropIndex && $action->getIndex()->getName() !== null):
                    $instructions->merge($this->getDropIndexByNameInstructions(
                        $table->getName(),
                        $action->getIndex()->getName()
                    ));
                    break;

                case ($action instanceof DropIndex && $action->getIndex()->getName() == null):
                    $instructions->merge($this->getDropIndexByColumnsInstructions(
                        $table->getName(),
                        $action->getIndex()->getColumns()
                    ));
                    break;

                case ($action instanceof DropTable):
                    $instructions->merge($this->getDropTableInstructions(
                        $table->getName()
                    ));
                    break;

                case ($action instanceof RemoveColumn):
                    $instructions->merge($this->getDropColumnInstructions(
                        $table->getName(),
                        $action->getColumn()->getName()
                    ));
                    break;

                case ($action instanceof RenameColumn):
                    $instructions->merge($this->getRenameColumnInstructions(
                        $table->getName(),
                        $action->getColumn()->getName(),
                        $action->getNewName()
                    ));
                    break;

                case ($action instanceof RenameTable):
                    $instructions->merge($this->getRenameTableInstructions(
                        $table->getName(),
                        $action->getNewName()
                    ));
                    break;

                case ($action instanceof ChangePrimaryKey):
                    $instructions->merge($this->getChangePrimaryKeyInstructions(
                        $table,
                        $action->getNewColumns()
                    ));
                    break;

                case ($action instanceof ChangeComment):
                    $instructions->merge($this->getChangeCommentInstructions(
                        $table,
                        $action->getNewComment()
                    ));
                    break;

                default:
                    throw new InvalidArgumentException(
                        sprintf("Don't know how to execute action: '%s'", get_class($action))
                    );
            }
        }

        $this->executeAlterSteps($table->getName(), $instructions);
    }
}
