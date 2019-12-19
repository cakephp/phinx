<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Seed;

use Phinx\Db\Adapter\AdapterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seed interface
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
interface SeedInterface
{
    /**
     * @var string
     */
    const RUN = 'run';

    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run();

    /**
     * Sets the database adapter.
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Database Adapter
     *
     * @return \Phinx\Seed\SeedInterface
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
     * @return \Phinx\Seed\SeedInterface
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
     * @return \Phinx\Seed\SeedInterface
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
     * @param string $sql SQL
     *
     * @return array
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
     * Insert data into a table.
     *
     * @param string $tableName
     * @param array $data
     *
     * @return void
     */
    public function insert($tableName, $data);

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
}
