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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
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
     * Class Constructor.
     *
     * @param string $environment Environment Detected
     * @param int $version Migration Version
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    final public function __construct($environment, $version, InputInterface $input = null, OutputInterface $output = null)
    {
        $this->environment = $environment;
        $this->version = $version;

        if (!is_null($input)) {
            $this->setInput($input);
        }

        if (!is_null($output)) {
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
     * {@inheritdoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return get_class($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     */
    public function setMigratingUp($isMigratingUp)
    {
        $this->isMigratingUp = $isMigratingUp;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isMigratingUp()
    {
        return $this->isMigratingUp;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($sql)
    {
        return $this->getAdapter()->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        return $this->getAdapter()->query($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryBuilder()
    {
        return $this->getAdapter()->getQueryBuilder();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchRow($sql)
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($sql)
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function createDatabase($name, $options)
    {
        $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * {@inheritdoc}
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
     * @param string $tableName Table Name
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
                method_exists($this, MigrationInterface::DOWN) ) {
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
     * @return void
     */
    public function postFlightCheck($direction = null)
    {
        foreach ($this->tables as $table) {
            if ($table->hasPendingActions()) {
                throw new \RuntimeException('Migration has pending actions after execution!');
            }
        }
    }
}
