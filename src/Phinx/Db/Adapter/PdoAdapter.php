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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use BadMethodCallException;
use Phinx\Db\Action\AddColumn;
use Phinx\Db\Action\AddForeignKey;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\ChangeColumn;
use Phinx\Db\Action\DropColumn;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Migration\MigrationInterface;

/**
 * Phinx PDO Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class PdoAdapter extends AbstractAdapter
{
    /**
     * @var \PDO|null
     */
    protected $connection;

    /**
     * {@inheritdoc}
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
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function setConnection(\PDO $connection)
    {
        $this->connection = $connection;

        // Create the schema table if it doesn't already exist
        if (!$this->hasSchemaTable()) {
            $this->createSchemaTable();
        } else {
            $table = new Table($this->getSchemaTableName(), [], $this);
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
        if ($this->isDryRunEnabled()) {
            $this->getOutput()->writeln($sql);

            return 0;
        }

        return $this->getConnection()->exec($sql);
    }

    /**
     * Executes a query and returns PDOStatement.
     *
     * @param string $sql SQL
     * @return \PDOStatement
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
        $rows = [];
        $result = $this->query($sql);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(Table $table, $row)
    {
        $sql = sprintf(
            "INSERT INTO %s ",
            $this->quoteTableName($table->getName())
        );

        $columns = array_keys($row);
        $sql .= "(" . implode(', ', array_map([$this, 'quoteColumnName'], $columns)) . ")";
        $sql .= " VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($row));
    }

    /**
     * {@inheritdoc}
     */
    public function bulkinsert(Table $table, $rows)
    {
        $sql = sprintf(
            "INSERT INTO %s ",
            $this->quoteTableName($table->getName())
        );

        $current = current($rows);
        $keys = array_keys($current);
        $sql .= "(" . implode(', ', array_map([$this, 'quoteColumnName'], $keys)) . ") VALUES";

        $vals = [];
        foreach ($rows as $row) {
            foreach ($row as $v) {
                $vals[] = $v;
            }
        }

        $count_keys = count($keys);
        $query = "(" . implode(', ', array_fill(0, $count_keys, '?')) . ")";

        $count_vars = count($rows);
        $queries = array_fill(0, $count_vars, $query);
        $sql .= implode(',', $queries);

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($vals);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersions()
    {
        $rows = $this->getVersionLog();

        return array_keys($rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionLog()
    {
        $result = [];

        switch ($this->options['version_order']) {
            case \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME:
                $orderBy = 'version ASC';
                break;
            case \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME:
                $orderBy = 'start_time ASC, version ASC';
                break;
            default:
                throw new \RuntimeException('Invalid version_order configuration option');
        }

        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->getSchemaTableName(), $orderBy));
        foreach ($rows as $version) {
            $result[$version['version']] = $version;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                "INSERT INTO %s (%s, %s, %s, %s, %s) VALUES ('%s', '%s', '%s', '%s', %s);",
                $this->getSchemaTableName(),
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
                $this->getSchemaTableName(),
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
                $this->getSchemaTableName(),
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
                $this->getSchemaTableName(),
                $this->quoteColumnName('breakpoint'),
                $this->castToBool(false),
                $this->quoteColumnName('start_time')
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createSchema($schemaName = 'public')
    {
        throw new BadMethodCallException('Creating a schema is not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function dropSchema($name)
    {
        throw new BadMethodCallException('Dropping a schema is not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return [
            'string',
            'char',
            'text',
            'integer',
            'biginteger',
            'bit',
            'float',
            'decimal',
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
     * {@inheritdoc}
     */
    public function castToBool($value)
    {
        return (bool)$value ? 1 : 0;
    }

    protected function executeAlterSteps($tableName, AlterInstructions $instructions)
    {
        $alter = sprintf(
            'ALTER TABLE %s %s',
            $tableName,
            implode(', ', $instructions->getAlterParts())
        );

        $this->execute($alter);

        foreach ($instructions->getPostSteps() as $sql) {
            $this->execute($sql);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $instructions = $this->getAddColumnInstructions($column);
        $this->executeAlterSteps($table, $instructions);
    }

    abstract protected function getAddColumnInstructions(Table $table, Column $column);

    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $instructions = $this->getRenameColumnInstructions($tableName, $columnName, $newColumnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName);

    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $instructions = $this->getChangeColumnInstructions($tableName, $columnName, $newColumn);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn);

    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $instructions = $this->getDropColumnInstructions($columnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getDropColumnInstructions($tableName, $columnName);

    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        $instructions = $this->getAddIndexInstructions($table, $index);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    abstract protected function getAddIndexInstructions(Table $table, Index $index);

    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        $instructions = $this->getDropIndexByColumnsInstructions($tableName, $columns);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getDropIndexByColumnsInstructions($tableName, $columns);

    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $instructions = $this->getDropIndexByNameInstructions($tableName, $indexName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getDropIndexByNameInstructions($tableName, $indexName);

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $instructions = $this->getAddForeignKeyInstructions($table, $foreignKey);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    abstract protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey);

    /**
     * {@inheritdoc}
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

    abstract protected function getDropForeignKeyInstructions($tableName, $constraint);

    abstract protected function getDropForeignKeyByColumnsInstructions($tableName, $columns);


    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $instructions = $this->getDropTableInstructions($tableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getDropTableInstructions($tableName);

    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $instructions = $this->getRenameTableInstructions($tableName, $newTableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    abstract protected function getRenameTableInstructions($tableName, $newTableName);

    public function executeActions(Table $table, array $actions)
    {
        $instructions = AlterInstructions();

        foreach ($actions as $action) {
            switch (true) {
                case ($action instanceof AddColumn):
                    $instructions->merge($this->getAddColumnInstructions($table, $action->getColumn()));
                    break;

                case ($action instanceof AddIndex):
                    $instructions->merge($this->getAddIndexInstructions($table, $action->getIndex()));
                    break;

                case ($action instanceof AddForeignKey):
                    $instructions->merge($this->getAddForeignKeyInstructions($table, $action->getColumn()));
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

                case ($action instanceof DropColumn):
                    $instructions->merge($this->getDropColumnInstructions(
                        $table->getName(),
                        $action->getColumnName()
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
                        $action->getIndex()->getPendingColumns()
                    ));
                    break;


                case ($action instanceof DropTable):
                    $instructions->merge($this->getDropTableInstructions(
                        $table->getName(),
                        $action->getColumn()->getName(),
                        $action->getNewName()
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

                default:
                    throw new \InvalidArgumentException(
                        sprintf("Don't know how to execute action: '%s'", get_class($action))
                    );
            }
        }

        $this->executeAlterSteps($table->getName(), $instructions);
    }
}
