<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Adapter;

use Exception;
use InvalidArgumentException;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Util\Literal;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base Abstract Database Adapter.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var string[]
     */
    protected $createdTables = [];

    /**
     * @var string
     */
    protected $schemaTableName = 'phinxlog';

    /**
     * @param array $options Options
     * @param \Symfony\Component\Console\Input\InputInterface|null $input Input Interface
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output Output Interface
     */
    public function __construct(array $options, InputInterface $input = null, OutputInterface $output = null)
    {
        $this->setOptions($options);
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
    public function setOptions(array $options)
    {
        $this->options = $options;

        if (isset($options['default_migration_table'])) {
            $this->setSchemaTableName($options['default_migration_table']);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * @inheritDoc
     */
    public function getOption($name)
    {
        if (!$this->hasOption($name)) {
            return null;
        }

        return $this->options[$name];
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
        if ($this->output === null) {
            $output = new NullOutput();
            $this->setOutput($output);
        }

        return $this->output;
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function getVersions()
    {
        $rows = $this->getVersionLog();

        return array_keys($rows);
    }

    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName()
    {
        return $this->schemaTableName;
    }

    /**
     * Sets the schema table name.
     *
     * @param string $schemaTableName Schema Table Name
     *
     * @return $this
     */
    public function setSchemaTableName($schemaTableName)
    {
        $this->schemaTableName = $schemaTableName;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasSchemaTable()
    {
        return $this->hasTable($this->getSchemaTableName());
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function createSchemaTable()
    {
        try {
            $options = [
                'id' => false,
                'primary_key' => 'version',
            ];

            $table = new Table($this->getSchemaTableName(), $options, $this);
            $table->addColumn('version', 'biginteger')
                ->addColumn('migration_name', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                ->addColumn('start_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('end_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('breakpoint', 'boolean', ['default' => false])
                ->save();
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'There was a problem creating the schema table: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getAdapterType()
    {
        return $this->getOption('adapter');
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(Column $column)
    {
        return $column->getType() instanceof Literal || in_array($column->getType(), $this->getColumnTypes());
    }

    /**
     * Determines if instead of executing queries a dump to standard output is needed
     *
     * @return bool
     */
    public function isDryRunEnabled()
    {
        $input = $this->getInput();

        return ($input && $input->hasOption('dry-run')) ? (bool)$input->getOption('dry-run') : false;
    }

    /**
     * Adds user-created tables (e.g. not phinxlog) to a cached list
     *
     * @param string $tableName The name of the table
     *
     * @return void
     */
    protected function addCreatedTable($tableName)
    {
        if (substr_compare($tableName, 'phinxlog', -strlen('phinxlog')) !== 0) {
            $this->createdTables[] = $tableName;
        }
    }

    /**
     * Updates the name of the cached table
     *
     * @param string $tableName Original name of the table
     * @param string $newTableName New name of the table
     *
     * @return void
     */
    protected function updateCreatedTableName($tableName, $newTableName)
    {
        $key = array_search($tableName, $this->createdTables);
        if ($key !== false) {
            $this->createdTables[$key] = $newTableName;
        }
    }

    /**
     * Removes table from the cached created list
     *
     * @param string $tableName The name of the table
     *
     * @return void
     */
    protected function removeCreatedTable($tableName)
    {
        $key = array_search($tableName, $this->createdTables);
        if ($key !== false) {
            unset($this->createdTables[$key]);
        }
    }

    /**
     * Check if the table is in the cached list of created tables
     *
     * @param string $tableName The name of the table
     *
     * @return bool
     */
    protected function hasCreatedTable($tableName)
    {
        return in_array($tableName, $this->createdTables);
    }
}
