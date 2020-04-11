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
 * Adapter Interface.
 *
 * @author Rob Morgan <robbym@gmail.com>
 * @method \PDO getConnection()
 */
interface AdapterInterface
{
    const PHINX_TYPE_STRING = 'string';
    const PHINX_TYPE_CHAR = 'char';
    const PHINX_TYPE_TEXT = 'text';
    const PHINX_TYPE_SMALL_INTEGER = 'smallinteger';
    const PHINX_TYPE_INTEGER = 'integer';
    const PHINX_TYPE_BIG_INTEGER = 'biginteger';
    const PHINX_TYPE_BIT = 'bit';
    const PHINX_TYPE_FLOAT = 'float';
    const PHINX_TYPE_DECIMAL = 'decimal';
    const PHINX_TYPE_DOUBLE = 'double';
    const PHINX_TYPE_DATETIME = 'datetime';
    const PHINX_TYPE_TIMESTAMP = 'timestamp';
    const PHINX_TYPE_TIME = 'time';
    const PHINX_TYPE_DATE = 'date';
    const PHINX_TYPE_BINARY = 'binary';
    const PHINX_TYPE_VARBINARY = 'varbinary';
    const PHINX_TYPE_BLOB = 'blob';
    const PHINX_TYPE_BOOLEAN = 'boolean';
    const PHINX_TYPE_JSON = 'json';
    const PHINX_TYPE_JSONB = 'jsonb';
    const PHINX_TYPE_UUID = 'uuid';
    const PHINX_TYPE_FILESTREAM = 'filestream';

    // Geospatial database types
    const PHINX_TYPE_GEOMETRY = 'geometry';
    const PHINX_TYPE_POINT = 'point';
    const PHINX_TYPE_LINESTRING = 'linestring';
    const PHINX_TYPE_POLYGON = 'polygon';

    // only for mysql so far
    const PHINX_TYPE_ENUM = 'enum';
    const PHINX_TYPE_SET = 'set';

    // only for postgresql so far
    const PHINX_TYPE_CIDR = 'cidr';
    const PHINX_TYPE_INET = 'inet';
    const PHINX_TYPE_MACADDR = 'macaddr';
    const PHINX_TYPE_INTERVAL = 'interval';

    /**
     * Get all migrated version numbers.
     *
     * @return array
     */
    public function getVersions();

    /**
     * Get all migration log entries, indexed by version creation time and sorted ascendingly by the configuration's
     * version order option
     *
     * @return array
     */
    public function getVersionLog();

    /**
     * Set adapter configuration options.
     *
     * @param array $options
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function setOptions(array $options);

    /**
     * Get all adapter options.
     *
     * @return array
     */
    public function getOptions();

    /**
     * Check if an option has been set.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasOption($name);

    /**
     * Get a single adapter option, or null if the option does not exist.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getOption($name);

    /**
     * Sets the console input.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function setInput(InputInterface $input);

    /**
     * Gets the console input.
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput();

    /**
     * Sets the console output.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function setOutput(OutputInterface $output);

    /**
     * Gets the console output.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput();

    /**
     * Returns a new Phinx\Db\Table\Column using the existent data domain.
     *
     * @param string $columnName The desired column name
     * @param string $type The type for the column. Can be a data domain type.
     * @param array $options Options array
     * @return \Phinx\Db\Table\Column
     */
    public function getColumnForType($columnName, $type, array $options);

    /**
     * Records a migration being run.
     *
     * @param \Phinx\Migration\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param string $startTime Start Time
     * @param string $endTime End Time
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime);

    /**
     * Toggle a migration breakpoint.
     *
     * @param \Phinx\Migration\MigrationInterface $migration
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function toggleBreakpoint(MigrationInterface $migration);

    /**
     * Reset all migration breakpoints.
     *
     * @return int The number of breakpoints reset
     */
    public function resetAllBreakpoints();

    /**
     * Set a migration breakpoint.
     *
     * @param \Phinx\Migration\MigrationInterface $migration The migration target for the breakpoint set
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function setBreakpoint(MigrationInterface $migration);

    /**
     * Unset a migration breakpoint.
     *
     * @param \Phinx\Migration\MigrationInterface $migration The migration target for the breakpoint unset
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function unsetBreakpoint(MigrationInterface $migration);

    /**
     * Does the schema table exist?
     *
     * @deprecated use hasTable instead.
     *
     * @return bool
     */
    public function hasSchemaTable();

    /**
     * Creates the schema table.
     *
     * @return void
     */
    public function createSchemaTable();

    /**
     * Returns the adapter type.
     *
     * @return string
     */
    public function getAdapterType();

    /**
     * Initializes the database connection.
     *
     * @throws \RuntimeException When the requested database driver is not installed.
     *
     * @return void
     */
    public function connect();

    /**
     * Closes the database connection.
     *
     * @return void
     */
    public function disconnect();

    /**
     * Does the adapter support transactions?
     *
     * @return bool
     */
    public function hasTransactions();

    /**
     * Begin a transaction.
     *
     * @return void
     */
    public function beginTransaction();

    /**
     * Commit a transaction.
     *
     * @return void
     */
    public function commitTransaction();

    /**
     * Rollback a transaction.
     *
     * @return void
     */
    public function rollbackTransaction();

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     *
     * @return int
     */
    public function execute($sql);

    /**
     * Executes a list of migration actions for the given table
     *
     * @param \Phinx\Db\Table\Table $table The table to execute the actions for
     * @param \Phinx\Db\Action\Action[] $actions The table to execute the actions for
     *
     * @return void
     */
    public function executeActions(Table $table, array $actions);

    /**
     * Returns a new Query object
     *
     * @return \Cake\Database\Query
     */
    public function getQueryBuilder();

    /**
     * Executes a SQL statement and returns the result as an array.
     *
     * @param string $sql SQL
     *
     * @return mixed
     */
    public function query($sql);

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     *
     * @return array
     */
    public function fetchRow($sql);

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     *
     * @return array
     */
    public function fetchAll($sql);

    /**
     * Inserts data into a table.
     *
     * @param \Phinx\Db\Table\Table $table Table where to insert data
     * @param array $row
     *
     * @return void
     */
    public function insert(Table $table, $row);

    /**
     * Inserts data into a table in a bulk.
     *
     * @param \Phinx\Db\Table\Table $table Table where to insert data
     * @param array $rows
     *
     * @return void
     */
    public function bulkinsert(Table $table, $rows);

    /**
     * Quotes a table name for use in a query.
     *
     * @param string $tableName Table Name
     *
     * @return string
     */
    public function quoteTableName($tableName);

    /**
     * Quotes a column name for use in a query.
     *
     * @param string $columnName Table Name
     *
     * @return string
     */
    public function quoteColumnName($columnName);

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table Name
     *
     * @return bool
     */
    public function hasTable($tableName);

    /**
     * Creates the specified database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Column[] $columns List of columns in the table
     * @param \Phinx\Db\Table\Index[] $indexes List of indexes for the table
     *
     * @return void
     */
    public function createTable(Table $table, array $columns = [], array $indexes = []);

    /**
     * Truncates the specified table
     *
     * @param string $tableName
     *
     * @return void
     */
    public function truncateTable($tableName);

    /**
     * Returns table columns
     *
     * @param string $tableName Table Name
     *
     * @return \Phinx\Db\Table\Column[]
     */
    public function getColumns($tableName);

    /**
     * Checks to see if a column exists.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     *
     * @return bool
     */
    public function hasColumn($tableName, $columnName);

    /**
     * Checks to see if an index exists.
     *
     * @param string $tableName Table Name
     * @param string|string[] $columns Column(s)
     *
     * @return bool
     */
    public function hasIndex($tableName, $columns);

    /**
     * Checks to see if an index specified by name exists.
     *
     * @param string $tableName Table Name
     * @param string $indexName
     *
     * @return bool
     */
    public function hasIndexByName($tableName, $indexName);

    /**
     * Checks to see if the specified primary key exists.
     *
     * @param string $tableName Table Name
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint name
     *
     * @return bool
     */
    public function hasPrimaryKey($tableName, $columns, $constraint = null);

    /**
     * Checks to see if a foreign key exists.
     *
     * @param string $tableName
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint name
     *
     * @return bool
     */
    public function hasForeignKey($tableName, $columns, $constraint = null);

    /**
     * Returns an array of the supported Phinx column types.
     *
     * @return array
     */
    public function getColumnTypes();

    /**
     * Checks that the given column is of a supported type.
     *
     * @param \Phinx\Db\Table\Column $column
     *
     * @return bool
     */
    public function isValidColumnType(Column $column);

    /**
     * Converts the Phinx logical type to the adapter's SQL type.
     *
     * @param string $type
     * @param int|null $limit
     *
     * @return array
     */
    public function getSqlType($type, $limit = null);

    /**
     * Creates a new database.
     *
     * @param string $name Database Name
     * @param array $options Options
     *
     * @return void
     */
    public function createDatabase($name, $options = []);

    /**
     * Checks to see if a database exists.
     *
     * @param string $name Database Name
     *
     * @return bool
     */
    public function hasDatabase($name);

    /**
     * Drops the specified database.
     *
     * @param string $name Database Name
     *
     * @return void
     */
    public function dropDatabase($name);

    /**
     * Creates the specified schema or throws an exception
     * if there is no support for it.
     *
     * @param string $schemaName Schema Name
     *
     * @return void
     */
    public function createSchema($schemaName = 'public');

    /**
     * Drops the specified schema table or throws an exception
     * if there is no support for it.
     *
     * @param string $schemaName Schema name
     *
     * @return void
     */
    public function dropSchema($schemaName);

    /**
     * Cast a value to a boolean appropriate for the adapter.
     *
     * @param mixed $value The value to be cast
     *
     * @return mixed
     */
    public function castToBool($value);
}
