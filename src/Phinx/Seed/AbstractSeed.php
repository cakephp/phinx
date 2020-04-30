<?php

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
 * Abstract Seed Class.
 *
 * It is expected that the seeds you write extend from this class.
 *
 * This abstract class proxies the various database methods to your specified
 * adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractSeed implements SeedInterface
{
    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Override to specify dependencies for dependency injection from the configured PSR-11 container
     */
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
    }

    /**
     * Return seeds dependencies.
     *
     * @return array
     */
    public function getDependencies()
    {
        return [];
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
        // convert to table object
        if (is_string($table)) {
            $table = new Table($table, [], $this->getAdapter());
        }
        $table->insert($data)->save();
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
        return new Table($tableName, $options, $this->getAdapter());
    }
}
