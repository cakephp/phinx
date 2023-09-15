<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Seed;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seed interface
 */
interface SeedInterface
{
    /**
     * @var string
     */
    public const RUN = 'run';

    /**
     * @var string
     */
    public const INIT = 'init';

    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Return seeds dependencies.
     *
     * @return array
     */
    public function getDependencies(): array;

    /**
     * Sets the environment.
     *
     * @return $this
     */
    public function setEnvironment(string $environment);

    /**
     * Gets the environment.
     *
     * @return string
     */
    public function getEnvironment(): string;

    /**
     * Sets the database adapter.
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Database Adapter
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter);

    /**
     * Gets the database adapter.
     *
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface;

    /**
     * Sets the input object to be used in migration object
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @return $this
     */
    public function setInput(InputInterface $input);

    /**
     * Gets the input object to be used in migration object
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput(): InputInterface;

    /**
     * Sets the output object to be used in migration object
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return $this
     */
    public function setOutput(OutputInterface $output);

    /**
     * Gets the output object to be used in migration object
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput(): OutputInterface;

    /**
     * Gets the name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return int
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Executes a SQL statement.
     *
     * The return type depends on the underlying adapter being used. To improve
     * IDE auto-completion possibility, you can overwrite the query method
     * phpDoc in your (typically custom abstract parent) seed class, where
     * you can set the return type by the adapter in your current use.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return mixed
     */
    public function query(string $sql, array $params = []): mixed;

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array|false
     */
    public function fetchRow(string $sql): array|false;

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll(string $sql): array;

    /**
     * Insert data into a table.
     *
     * @param string $tableName Table name
     * @param array $data Data
     * @return void
     */
    public function insert(string $tableName, array $data): void;

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table name
     * @return bool
     */
    public function hasTable(string $tableName): bool;

    /**
     * Returns an instance of the <code>\Table</code> class.
     *
     * You can use this class to create and manipulate tables.
     *
     * @param string $tableName Table name
     * @param array<string, mixed> $options Options
     * @return \Phinx\Db\Table
     */
    public function table(string $tableName, array $options): Table;

    /**
     * Checks to see if the seed should be executed.
     *
     * Returns true by default.
     *
     * You can use this to prevent a seed from executing.
     *
     * @return bool
     */
    public function shouldExecute(): bool;
}
