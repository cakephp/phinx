<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Migration;

use Phinx\Db\Adapter\AdapterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migration interface
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
interface MigrationInterface
{
    /**
     * @var string
     */
    const CHANGE = 'change';

    /**
     * @var string
     */
    const UP = 'up';

    /**
     * @var string
     */
    const DOWN = 'down';

    /**
     * Sets the database adapter.
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Database Adapter
     *
     * @return \Phinx\Migration\MigrationInterface
     */
    public function setAdapter(AdapterInterface $adapter);

    /**
     * Gets the database adapter.
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getAdapter();

    /**
     * Sets the input object to be used in migration object
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Phinx\Migration\MigrationInterface
     */
    public function setInput(InputInterface $input);

    /**
     * Gets the input object to be used in migration object
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput();

    /**
     * Sets the output object to be used in migration object
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Phinx\Migration\MigrationInterface
     */
    public function setOutput(OutputInterface $output);

    /**
     * Gets the output object to be used in migration object
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput();

    /**
     * Gets the name.
     *
     * @return string
     */
    public function getName();

    /**
     * Gets the detected environment
     *
     * @return string
     */
    public function getEnvironment();

    /**
     * Sets the migration version number.
     *
     * @param float $version Version
     *
     * @return \Phinx\Migration\MigrationInterface
     */
    public function setVersion($version);

    /**
     * Gets the migration version number.
     *
     * @return float
     */
    public function getVersion();

    /**
     * Sets whether this migration is being applied or reverted
     *
     * @param bool $isMigratingUp True if the migration is being applied
     *
     * @return \Phinx\Migration\MigrationInterface
     */
    public function setMigratingUp($isMigratingUp);

    /**
     * Gets whether this migration is being applied or reverted.
     * True means that the migration is being applied.
     *
     * @return bool
     */
    public function isMigratingUp();

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     *
     * @return int
     */
    public function execute($sql);

    /**
     * Executes a SQL statement and returns the result as an array.
     *
     * To improve IDE auto-completion possibility, you can overwrite the query method
     * phpDoc in your (typically custom abstract parent) migration class, where you can set
     * the return type by the adapter in your current use.
     *
     * @param string $sql SQL
     *
     * @return mixed
     */
    public function query($sql);

    /**
     * Returns a new Query object that can be used to build complex SELECT, UPDATE, INSERT or DELETE
     * queries and execute them against the current database.
     *
     * Queries executed through the query builder are always sent to the database, regardless of the
     * the dry-run settings.
     *
     * @see https://api.cakephp.org/3.6/class-Cake.Database.Query.html
     *
     * @return \Cake\Database\Query
     */
    public function getQueryBuilder();

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
     * Insert data into a table.
     *
     * @deprecated since 0.10.0. Use $this->table($tableName)->insert($data)->save() instead.
     *
     * @param string $tableName
     * @param array $data
     *
     * @return void
     */
    public function insert($tableName, $data);

    /**
     * Create a new database.
     *
     * @param string $name Database Name
     * @param array $options Options
     *
     * @return void
     */
    public function createDatabase($name, $options);

    /**
     * Drop a database.
     *
     * @param string $name Database Name
     *
     * @return void
     */
    public function dropDatabase($name);

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table Name
     *
     * @return bool
     */
    public function hasTable($tableName);

    /**
     * Returns an instance of the <code>\Table</code> class.
     *
     * You can use this class to create and manipulate tables.
     *
     * @param string $tableName Table Name
     * @param array $options Options
     *
     * @return \Phinx\Db\Table
     */
    public function table($tableName, $options);

    /**
     * Perform checks on the migration, print a warning
     * if there are potential problems.
     *
     * @param string|null $direction
     *
     * @return void
     */
    public function preFlightCheck($direction = null);

    /**
     * Perform checks on the migration after completion
     *
     * Right now, the only check is whether all changes were committed
     *
     * @param string|null $direction direction of migration
     *
     * @return void
     */
    public function postFlightCheck($direction = null);
}
