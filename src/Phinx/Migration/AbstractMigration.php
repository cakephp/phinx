<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Migration;

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
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @var string
     */
    protected $environment;

    /**
     * @var float
     */
    protected $version;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * Whether this migration is being applied or reverted
     *
     * @var bool
     */
    protected $isMigratingUp = true;

    /**
     * List of all the table objects created by this migration
     *
     * @var array
     */
    protected $tables = [];

    /**
     * @param string $environment Environment Detected
     * @param int $version Migration Version
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    final public function __construct($environment, $version, InputInterface $input = null, OutputInterface $output = null)
    {
        $this->environment = $environment;
        $this->version = $version;

        if ($input !== null) {
            $this->setInput($input);
        }

        if ($output !== null) {
            $this->setOutput($output);
        }

        $this->init();
    }

    /**
     * Initialize method.
     *
     * @return void
     */
    protected function init()
    {
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
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return static::class;
    }

    /**
     * @inheritDoc
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @inheritDoc
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @inheritDoc
     */
    public function setMigratingUp($isMigratingUp)
    {
        $this->isMigratingUp = $isMigratingUp;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isMigratingUp()
    {
        return $this->isMigratingUp;
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
     * @inheritDoc
     */
    public function getQueryBuilder()
    {
        return $this->getAdapter()->getQueryBuilder();
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
    public function insert($table, $data)
    {
        trigger_error('insert() is deprecated since 0.10.0. Use $this->table($tableName)->insert($data)->save() instead.', E_USER_DEPRECATED);
        // convert to table object
        if (is_string($table)) {
            $table = new Table($table, [], $this->getAdapter());
        }
        $table->insert($data)->save();
    }

    /**
     * @inheritDoc
     */
    public function createDatabase($name, $options)
    {
        $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase($name)
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * @inheritDoc
     */
    public function hasTable($tableName)
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function table($tableName, $options = [])
    {
        $table = new Table($tableName, $options, $this->getAdapter());
        $this->tables[] = $table;

        return $table;
    }

    /**
     * A short-hand method to drop the given database table.
     *
     * @deprecated since 0.10.0. Use $this->table($tableName)->drop()->save() instead.
     *
     * @param string $tableName Table Name
     *
     * @return void
     */
    public function dropTable($tableName)
    {
        trigger_error('dropTable() is deprecated since 0.10.0. Use $this->table($tableName)->drop()->save() instead.', E_USER_DEPRECATED);
        $this->table($tableName)->drop()->save();
    }

    /**
     * Perform checks on the migration, print a warning
     * if there are potential problems.
     *
     * Right now, the only check is if there is both a `change()` and
     * an `up()` or a `down()` method.
     *
     * @param string|null $direction
     *
     * @return void
     */
    public function preFlightCheck($direction = null)
    {
        if (method_exists($this, MigrationInterface::CHANGE)) {
            if (method_exists($this, MigrationInterface::UP) ||
                method_exists($this, MigrationInterface::DOWN)
            ) {
                $this->output->writeln(sprintf(
                    '<comment>warning</comment> Migration contains both change() and/or up()/down() methods.  <options=bold>Ignoring up() and down()</>.'
                ));
            }
        }
    }

    /**
     * Perform checks on the migration after completion
     *
     * Right now, the only check is whether all changes were committed
     *
     * @param string|null $direction direction of migration
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function postFlightCheck($direction = null)
    {
        foreach ($this->tables as $table) {
            if ($table->hasPendingActions()) {
                throw new RuntimeException('Migration has pending actions after execution!');
            }
        }
    }
}
