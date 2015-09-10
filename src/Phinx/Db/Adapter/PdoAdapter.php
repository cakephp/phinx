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

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Migration\MigrationInterface;

/**
 * Phinx PDO Adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class PdoAdapter implements AdapterInterface
{
    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $schemaTableName = 'phinxlog';

    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * @var float
     */
    protected $commandStartTime;

    /**
     * Class Constructor.
     *
     * @param array $options Options
     * @param OutputInterface $output Output Interface
     */
    public function __construct(array $options, OutputInterface $output = null)
    {
        $this->setOptions($options);
        if (null !== $output) {
            $this->setOutput($output);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        if (isset($options['default_migration_table'])) {
            $this->setSchemaTableName($options['default_migration_table']);
        }

        if (isset($options['connection'])) {
            $this->setConnection($options['connection']);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name)
    {
        if (!$this->hasOption($name)) {
            return null;
        }
        return $this->options[$name];
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
        if (null == $this->output) {
            $output = new NullOutput();
            $this->setOutput($output);
        }
        return $this->output;
    }

    /**
     * Sets the schema table name.
     *
     * @param string $schemaTableName Schema Table Name
     * @return PdoAdapter
     */
    public function setSchemaTableName($schemaTableName)
    {
        $this->schemaTableName = $schemaTableName;
        return $this;
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
     * Sets the database connection.
     *
     * @param \PDO $connection Connection
     * @return AdapterInterface
     */
    public function setConnection(\PDO $connection)
    {
        $this->connection = $connection;

        // Create the schema table if it doesn't already exist
        if (!$this->hasSchemaTable()) {
            $this->createSchemaTable();
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
        if (null === $this->connection) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Sets the command start time
     *
     * @param int $time
     * @return AdapterInterface
     */
    public function setCommandStartTime($time)
    {
        $this->commandStartTime = $time;
        return $this;
    }

    /**
     * Gets the command start time
     *
     * @return int
     */
    public function getCommandStartTime()
    {
        return $this->commandStartTime;
    }

    /**
     * Start timing a command.
     *
     * @return void
     */
    public function startCommandTimer()
    {
        $this->setCommandStartTime(microtime(true));
    }

    /**
     * Stop timing the current command and write the elapsed time to the
     * output.
     *
     * @return void
     */
    public function endCommandTimer()
    {
        $end = microtime(true);
        if (OutputInterface::VERBOSITY_VERBOSE === $this->getOutput()->getVerbosity()) {
            $this->getOutput()->writeln('    -> ' . sprintf('%.4fs', $end - $this->getCommandStartTime()));
        }
    }

    /**
     * Write a Phinx command to the output.
     *
     * @param string $command Command Name
     * @param array  $args    Command Args
     * @return void
     */
    public function writeCommand($command, $args = array())
    {
        if (OutputInterface::VERBOSITY_VERBOSE === $this->getOutput()->getVerbosity()) {
            if (count($args)) {
                $outArr = array();
                foreach ($args as $arg) {
                    if (is_array($arg)) {
                        $arg = array_map(function ($value) {
                            return '\'' . $value . '\'';
                        }, $arg);
                        $outArr[] = '[' . implode(', ', $arg)  . ']';
                        continue;
                    }

                    $outArr[] = '\'' . $arg . '\'';
                }
                $this->getOutput()->writeln(' -- ' . $command . '(' . implode(', ', $outArr) . ')');
                return;
            }
            $this->getOutput()->writeln(' -- ' . $command);
        }
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
        return $this->getConnection()->exec($sql);
    }

    /**
     * {@inheritdoc}
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
        $rows = array();
        $result = $this->query($sql);
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(Table $table, $columns, $data)
    {
        $this->startCommandTimer();

        $sql = sprintf(
            "INSERT INTO %s ",
            $this->quoteTableName($table->getName())
        );

        $sql .= "(". implode(', ', array_map(array($this, 'quoteColumnName'), $columns)) . ")";
        $sql .= " VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";

        $stmt = $this->getConnection()->prepare($sql);

        foreach ($data as $row) {
            $stmt->execute($row);
        }

        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function getVersions()
    {
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY version ASC', $this->getSchemaTableName()));
        return array_map(
            function ($v) {
                return $v['version'];
            },
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                'INSERT INTO %s ('
                . 'version, start_time, end_time'
                . ') VALUES ('
                . '\'%s\','
                . '\'%s\','
                . '\'%s\''
                . ');',
                $this->getSchemaTableName(),
                $migration->getVersion(),
                $startTime,
                $endTime
            );

            $this->query($sql);
        } else {
            // down
            $sql = sprintf(
                "DELETE FROM %s WHERE version = '%s'",
                $this->getSchemaTableName(),
                $migration->getVersion()
            );

            $this->query($sql);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSchemaTable()
    {
        return $this->hasTable($this->getSchemaTableName());
    }

    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {
        try {
            $options = array(
                'id'          => false,
                'primary_key' => 'version'
            );

            $table = new Table($this->getSchemaTableName(), $options, $this);

            if ($this->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql'
                && version_compare($this->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.0', '>=')) {
                $table->addColumn('version', 'biginteger', array('limit' => 14))
                      ->addColumn('start_time', 'timestamp', array('default' => 'CURRENT_TIMESTAMP'))
                      ->addColumn('end_time', 'timestamp', array('default' => 'CURRENT_TIMESTAMP'))
                      ->save();
            } else {
                $table->addColumn('version', 'biginteger')
                      ->addColumn('start_time', 'timestamp')
                      ->addColumn('end_time', 'timestamp')
                      ->save();
            }
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('There was a problem creating the schema table: ' . $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapterType()
    {
        return $this->getOption('adapter');
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array(
            'string',
            'char',
            'text',
            'integer',
            'biginteger',
            'float',
            'decimal',
            'datetime',
            'timestamp',
            'time',
            'date',
            'binary',
            'boolean',
            'uuid',
            // Geospatial data types
            'geometry',
            'point',
            'linestring',
            'polygon',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column) {
        return in_array($column->getType(), $this->getColumnTypes());
    }
}
