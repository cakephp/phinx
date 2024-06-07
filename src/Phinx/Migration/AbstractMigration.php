<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Migration;

use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract Migration Class.
 *
 * It is expected that the migrations you write extend from this class.
 *
 * This abstract class proxies the various database methods to your specified
 * adapter.
 */
abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @var string
     */
    protected string $environment;

    /**
     * @var int
     */
    protected int $version;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface|null
     */
    protected ?AdapterInterface $adapter = null;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface|null
     */
    protected ?OutputInterface $output = null;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface|null
     */
    protected ?InputInterface $input = null;

    /**
     * Whether this migration is being applied or reverted
     *
     * @var bool
     */
    protected bool $isMigratingUp = true;

    /**
     * List of all the table objects created by this migration
     *
     * @var array<\Phinx\Db\Table>
     */
    protected array $tables = [];

    /**
     * @param string $environment Environment Detected
     * @param int $version Migration Version
     * @param \Symfony\Component\Console\Input\InputInterface|null $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output Output
     */
    final public function __construct(string $environment, int $version, ?InputInterface $input = null, ?OutputInterface $output = null)
    {
        $this->validateVersion($version);

        $this->environment = $environment;
        $this->version = $version;

        if ($input !== null) {
            $this->setInput($input);
        }

        if ($output !== null) {
            $this->setOutput($output);
        }
    }

    /**
     * @inheritDoc
     */
    public function setAdapter(AdapterInterface $adapter): MigrationInterface
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAdapter(): AdapterInterface
    {
        if (!isset($this->adapter)) {
            throw new RuntimeException('Cannot access `adapter` it has not been set');
        }

        return $this->adapter;
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input): MigrationInterface
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInput(): ?InputInterface
    {
        return $this->input;
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output): MigrationInterface
    {
        $this->output = $output;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): ?OutputInterface
    {
        return $this->output;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * @inheritDoc
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * @inheritDoc
     */
    public function setVersion($version): MigrationInterface
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @inheritDoc
     */
    public function setMigratingUp(bool $isMigratingUp): MigrationInterface
    {
        $this->isMigratingUp = $isMigratingUp;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isMigratingUp(): bool
    {
        return $this->isMigratingUp;
    }

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->getAdapter()->execute($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->getAdapter()->query($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder(string $type): Query
    {
        return $this->getAdapter()->getQueryBuilder($type);
    }

    /**
     * @inheritDoc
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->getAdapter()->getSelectBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->getAdapter()->getInsertBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->getAdapter()->getUpdateBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->getAdapter()->getDeleteBuilder();
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string $sql): array
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options): void
    {
        $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * @inheritDoc
     */
    public function createSchema(string $name): void
    {
        $this->getAdapter()->createSchema($name);
    }

    /**
     * @inheritDoc
     */
    public function dropSchema(string $name): void
    {
        $this->getAdapter()->dropSchema($name);
    }

    /**
     * @inheritDoc
     */
    public function hasTable(string $tableName): bool
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function table(string $tableName, array $options = []): Table
    {
        $table = new Table($tableName, $options, $this->getAdapter());
        $this->tables[] = $table;

        return $table;
    }

    /**
     * Perform checks on the migration, print a warning
     * if there are potential problems.
     *
     * Right now, the only check is if there is both a `change()` and
     * an `up()` or a `down()` method.
     *
     * @return void
     */
    public function preFlightCheck(): void
    {
        if (method_exists($this, MigrationInterface::CHANGE)) {
            if (
                method_exists($this, MigrationInterface::UP) ||
                method_exists($this, MigrationInterface::DOWN)
            ) {
                $this->output->writeln(sprintf(
                    '<comment>warning</comment> Migration contains both change() and up()/down() methods.  <options=bold>Ignoring up() and down()</>.'
                ));
            }
        }
    }

    /**
     * Perform checks on the migration after completion
     *
     * Right now, the only check is whether all changes were committed
     *
     * @throws \RuntimeException
     * @return void
     */
    public function postFlightCheck(): void
    {
        foreach ($this->tables as $table) {
            if ($table->hasPendingActions()) {
                throw new RuntimeException(sprintf('Migration %s_%s has pending actions after execution!', $this->getVersion(), $this->getName()));
            }
        }
    }

    /**
     * Checks to see if the migration should be executed.
     *
     * Returns true by default.
     *
     * You can use this to prevent a migration from executing.
     *
     * @return bool
     */
    public function shouldExecute(): bool
    {
        return true;
    }

    /**
     * Makes sure the version int is within range for valid datetime.
     * This is required to have a meaningful order in the overview.
     *
     * @param int $version Version
     * @return void
     */
    protected function validateVersion(int $version): void
    {
        $length = strlen((string)$version);
        if ($length === 14) {
            return;
        }

        throw new RuntimeException('Invalid version `' . $version . '`, should be in format `YYYYMMDDHHMMSS` (length of 14).');
    }
}
