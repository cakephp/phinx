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
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * Writes a message to stdout if verbose output is on
     *
     * @param string $message The message to show
     * @return void
     */
    protected function verboseLog($message)
    {
        if (!$this->isDryRunEnabled() &&
             $this->getOutput()->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE) {
            return;
        }

        $this->getOutput()->writeln($message);
    }

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
            $table = new \Phinx\Db\Table($this->getSchemaTableName(), [], $this);
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
     * {@inheritdoc}
     */
    public function getQueryBuilder()
    {
        return $this->getDecoratedConnection()->newQuery();
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
     * @param mixed $value  The value to quote
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
     * @param string $value  The string to quote
     * @return string
     */
    protected function quoteString($value)
    {
        return $this->getConnection()->quote($value);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function castToBool($value)
    {
        return (bool)$value ? 1 : 0;
    }

    /**
     * Retrieve a database connection attribute
     * @see http://php.net/manual/en/pdo.getattribute.php
     *
     * @param int $attribute One of the PDO::ATTR_* constants
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        return $this->connection->getAttribute($attribute);
    }

    /**
     * Get the definition for a `DEFAULT` statement.
     *
     * @param  mixed $default Default value
     * @param string $columnType column type added
     * @return string
     */
    protected function getDefaultValueDefinition($default, $columnType = null)
    {
        if (is_string($default) && 'CURRENT_TIMESTAMP' !== $default) {
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
     * @param AlterInstructions $instructions The object containing the alter sequence
     * @return void
     */
    protected function executeAlterSteps($tableName, AlterInstructions $instructions)
    {
        $alter = sprintf('ALTER TABLE %s %%s', $this->quoteTableName($tableName));
        $instructions->execute($alter, [$this, 'execute']);
    }
}
