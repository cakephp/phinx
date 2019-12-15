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
 * @subpackage Phinx\Db
 */
namespace Phinx\Db;

use Phinx\Db\Action\AddColumn;
use Phinx\Db\Action\AddForeignKey;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\ChangeColumn;
use Phinx\Db\Action\ChangeComment;
use Phinx\Db\Action\ChangePrimaryKey;
use Phinx\Db\Action\CreateTable;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Plan\Intent;
use Phinx\Db\Plan\Plan;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table as TableValue;

/**
 *
 * This object is based loosely on: http://api.rubyonrails.org/classes/ActiveRecord/ConnectionAdapters/Table.html.
 */
class Table
{
    /**
     * @var \Phinx\Db\Table\Table
     */
    protected $table;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * @var \Phinx\Db\Plan\Intent
     */
    protected $actions;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * Class Constructor.
     *
     * @param string $name Table Name
     * @param array $options Options
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Database Adapter
     */
    public function __construct($name, $options = [], AdapterInterface $adapter = null)
    {
        $this->table = new TableValue($name, $options);
        $this->actions = new Intent();

        if ($adapter !== null) {
            $this->setAdapter($adapter);
        }
    }

    /**
     * Gets the table name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->table->getName();
    }

    /**
     * Gets the table options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->table->getOptions();
    }

    /**
     * Gets the table name and options as an object
     *
     * @return \Phinx\Db\Table\Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Sets the database adapter.
     *
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter Database Adapter
     * @return \Phinx\Db\Table
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return \Phinx\Db\Adapter\AdapterInterface|null
     */
    public function getAdapter()
    {
        if (!$this->adapter) {
            throw new \RuntimeException('There is no database adapter set yet, cannot proceed');
        }

        return $this->adapter;
    }

    /**
     * Does the table have pending actions?
     *
     * @return bool
     */
    public function hasPendingActions()
    {
        return count($this->actions->getActions()) > 0 || count($this->data) > 0;
    }

    /**
     * Does the table exist?
     *
     * @return bool
     */
    public function exists()
    {
        return $this->getAdapter()->hasTable($this->getName());
    }

    /**
     * Drops the database table.
     *
     * @return \Phinx\Db\Table
     */
    public function drop()
    {
        $this->actions->addAction(new DropTable($this->table));

        return $this;
    }

    /**
     * Renames the database table.
     *
     * @param string $newTableName New Table Name
     * @return \Phinx\Db\Table
     */
    public function rename($newTableName)
    {
        $this->actions->addAction(new RenameTable($this->table, $newTableName));

        return $this;
    }

    /**
     * Changes the primary key of the database table.
     *
     * @param string|array|null $columns Column name(s) to belong to the primary key, or null to drop the key
     * @return $this
     */
    public function changePrimaryKey($columns)
    {
        $this->actions->addAction(new ChangePrimaryKey($this->table, $columns));

        return $this;
    }

    /**
     * Changes the comment of the database table.
     *
     * @param string|null $comment New comment string, or null to drop the comment
     * @return $this
     */
    public function changeComment($comment)
    {
        $this->actions->addAction(new ChangeComment($this->table, $comment));

        return $this;
    }

    /**
     * Gets an array of the table columns.
     *
     * @return \Phinx\Db\Table\Column[]
     */
    public function getColumns()
    {
        return $this->getAdapter()->getColumns($this->getName());
    }

    /**
     * Gets a table column if it exists.
     *
     * @param string $name Column name
     * @return \Phinx\Db\Table\Column|null
     */
    public function getColumn($name)
    {
        $columns = array_filter(
            $this->getColumns(),
            function ($column) use ($name) {
                return $column->getName() === $name;
            }
        );

        return array_pop($columns);
    }

    /**
     * Sets an array of data to be inserted.
     *
     * @param array $data Data
     * @return \Phinx\Db\Table
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Gets the data waiting to be inserted.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Resets all of the pending data to be inserted
     *
     * @return void
     */
    public function resetData()
    {
        $this->setData([]);
    }

    /**
     * Resets all of the pending table changes.
     *
     * @return void
     */
    public function reset()
    {
        $this->actions = new Intent();
        $this->resetData();
    }

    /**
     * Add a table column.
     *
     * Type can be: string, text, integer, float, decimal, datetime, timestamp,
     * time, date, binary, boolean.
     *
     * Valid options can be: limit, default, null, precision or scale.
     *
     * @param string|\Phinx\Db\Table\Column $columnName Column Name
     * @param string|\Phinx\Util\Literal $type Column Type
     * @param array $options Column Options
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return \Phinx\Db\Table
     */
    public function addColumn($columnName, $type = null, $options = [])
    {
        if ($columnName instanceof Column) {
            $action = new AddColumn($this->table, $columnName);
        } else {
            $action = AddColumn::build($this->table, $columnName, $type, $options);
        }

        // Delegate to Adapters to check column type
        if (!$this->getAdapter()->isValidColumnType($action->getColumn())) {
            throw new \InvalidArgumentException(sprintf(
                'An invalid column type "%s" was specified for column "%s".',
                $type,
                $action->getColumn()->getName()
            ));
        }

        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Remove a table column.
     *
     * @param string $columnName Column Name
     * @return \Phinx\Db\Table
     */
    public function removeColumn($columnName)
    {
        $action = RemoveColumn::build($this->table, $columnName);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Rename a table column.
     *
     * @param string $oldName Old Column Name
     * @param string $newName New Column Name
     * @return \Phinx\Db\Table
     */
    public function renameColumn($oldName, $newName)
    {
        $action = RenameColumn::build($this->table, $oldName, $newName);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Change a table column type.
     *
     * @param string        $columnName    Column Name
     * @param string|\Phinx\Db\Table\Column|\Phinx\Util\Literal $newColumnType New Column Type
     * @param array         $options       Options
     * @return \Phinx\Db\Table
     */
    public function changeColumn($columnName, $newColumnType, array $options = [])
    {
        if ($newColumnType instanceof Column) {
            $action = new ChangeColumn($this->table, $columnName, $newColumnType);
        } else {
            $action = ChangeColumn::build($this->table, $columnName, $newColumnType, $options);
        }
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Checks to see if a column exists.
     *
     * @param string $columnName Column Name
     * @return bool
     */
    public function hasColumn($columnName)
    {
        return $this->getAdapter()->hasColumn($this->getName(), $columnName);
    }

    /**
     * Add an index to a database table.
     *
     * In $options you can specific unique = true/false or name (index name).
     *
     * @param string|array|\Phinx\Db\Table\Index $columns Table Column(s)
     * @param array $options Index Options
     * @return \Phinx\Db\Table
     */
    public function addIndex($columns, array $options = [])
    {
        $action = AddIndex::build($this->table, $columns, $options);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Removes the given index from a table.
     *
     * @param string|array $columns Columns
     * @return \Phinx\Db\Table
     */
    public function removeIndex($columns)
    {
        $action = DropIndex::build($this->table, is_string($columns) ? [$columns] : $columns);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Removes the given index identified by its name from a table.
     *
     * @param string $name Index name
     * @return \Phinx\Db\Table
     */
    public function removeIndexByName($name)
    {
        $action = DropIndex::buildFromName($this->table, $name);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Checks to see if an index exists.
     *
     * @param string|array $columns Columns
     * @return bool
     */
    public function hasIndex($columns)
    {
        return $this->getAdapter()->hasIndex($this->getName(), $columns);
    }

    /**
     * Checks to see if an index specified by name exists.
     *
     * @param string $indexName
     * @return bool
     */
    public function hasIndexByName($indexName)
    {
        return $this->getAdapter()->hasIndexByName($this->getName(), $indexName);
    }

    /**
     * Add a foreign key to a database table.
     *
     * In $options you can specify on_delete|on_delete = cascade|no_action ..,
     * on_update, constraint = constraint name.
     *
     * @param string|array $columns Columns
     * @param string|\Phinx\Db\Table $referencedTable   Referenced Table
     * @param string|array $referencedColumns Referenced Columns
     * @param array $options Options
     * @return \Phinx\Db\Table
     */
    public function addForeignKey($columns, $referencedTable, $referencedColumns = ['id'], $options = [])
    {
        $action = AddForeignKey::build($this->table, $columns, $referencedTable, $referencedColumns, $options);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Add a foreign key to a database table with a given name.
     *
     * In $options you can specify on_delete|on_delete = cascade|no_action ..,
     * on_update, constraint = constraint name.
     *
     * @param string $name The constraint name
     * @param string|array $columns Columns
     * @param string|\Phinx\Db\Table $referencedTable   Referenced Table
     * @param string|array $referencedColumns Referenced Columns
     * @param array $options Options
     * @return \Phinx\Db\Table
     */
    public function addForeignKeyWithName($name, $columns, $referencedTable, $referencedColumns = ['id'], $options = [])
    {
        $action = AddForeignKey::build(
            $this->table,
            $columns,
            $referencedTable,
            $referencedColumns,
            $options,
            $name
        );
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Removes the given foreign key from the table.
     *
     * @param string|array $columns    Column(s)
     * @param null|string  $constraint Constraint names
     * @return \Phinx\Db\Table
     */
    public function dropForeignKey($columns, $constraint = null)
    {
        $action = DropForeignKey::build($this->table, $columns, $constraint);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Checks to see if a foreign key exists.
     *
     * @param  string|array $columns    Column(s)
     * @param  null|string  $constraint Constraint names
     * @return bool
     */
    public function hasForeignKey($columns, $constraint = null)
    {
        return $this->getAdapter()->hasForeignKey($this->getName(), $columns, $constraint);
    }

    /**
     * Add timestamp columns created_at and updated_at to the table.
     *
     * @param string|null $createdAt    Alternate name for the created_at column
     * @param string|null $updatedAt    Alternate name for the updated_at column
     * @param bool        $withTimezone Whether to set the timezone option on the added columns
     *
     * @return \Phinx\Db\Table
     */
    public function addTimestamps($createdAt = 'created_at', $updatedAt = 'updated_at', $withTimezone = false)
    {
        $createdAt = is_null($createdAt) ? 'created_at' : $createdAt;
        $updatedAt = is_null($updatedAt) ? 'updated_at' : $updatedAt;

        $this->addColumn($createdAt, 'timestamp', [
                   'default' => 'CURRENT_TIMESTAMP',
                   'update' => '',
                   'timezone' => $withTimezone,
             ])
             ->addColumn($updatedAt, 'timestamp', [
                 'null' => true,
                 'default' => null,
                 'timezone' => $withTimezone,
             ]);

        return $this;
    }

    /**
     * Alias that always sets $withTimezone to true
     * @see addTimestamps
     *
     * @param string|null $createdAt Alternate name for the created_at column
     * @param string|null $updatedAt Alternate name for the updated_at column
     *
     * @return \Phinx\Db\Table
     */
    public function addTimestampsWithTimezone($createdAt = null, $updatedAt = null)
    {
        $this->addTimestamps($createdAt, $updatedAt, true);

        return $this;
    }

    /**
     * Insert data into the table.
     *
     * @param array $data array of data in the form:
     *              array(
     *                  array("col1" => "value1", "col2" => "anotherValue1"),
     *                  array("col2" => "value2", "col2" => "anotherValue2"),
     *              )
     *              or array("col1" => "value1", "col2" => "anotherValue1")
     *
     * @return \Phinx\Db\Table
     */
    public function insert($data)
    {
        // handle array of array situations
        $keys = array_keys($data);
        $firstKey = array_shift($keys);
        if ($firstKey !== null && is_array($data[$firstKey])) {
            foreach ($data as $row) {
                $this->data[] = $row;
            }

            return $this;
        }

        if (count($data) > 0) {
            $this->data[] = $data;
        }

        return $this;
    }

    /**
     * Creates a table from the object instance.
     *
     * @return void
     */
    public function create()
    {
        $this->executeActions(false);
        $this->saveData();
        $this->reset(); // reset pending changes
    }

    /**
     * Updates a table from the object instance.
     *
     * @throws \RuntimeException
     * @return void
     */
    public function update()
    {
        $this->executeActions(true);
        $this->saveData();
        $this->reset(); // reset pending changes
    }

    /**
     * Commit the pending data waiting for insertion.
     *
     * @return void
     */
    public function saveData()
    {
        $rows = $this->getData();
        if (empty($rows)) {
            return;
        }

        $bulk = true;
        $row = current($rows);
        $c = array_keys($row);
        foreach ($this->getData() as $row) {
            $k = array_keys($row);
            if ($k != $c) {
                $bulk = false;
                break;
            }
        }

        if ($bulk) {
            $this->getAdapter()->bulkinsert($this->table, $this->getData());
        } else {
            foreach ($this->getData() as $row) {
                $this->getAdapter()->insert($this->table, $row);
            }
        }

        $this->resetData();
    }

    /**
     * Immediately truncates the table. This operation cannot be undone
     *
     * @return void
     */
    public function truncate()
    {
        $this->getAdapter()->truncateTable($this->getName());
    }

    /**
     * Commits the table changes.
     *
     * If the table doesn't exist it is created otherwise it is updated.
     *
     * @return void
     */
    public function save()
    {
        if ($this->exists()) {
            $this->update(); // update the table
        } else {
            $this->create(); // create the table
        }
    }

    /**
     * Executes all the pending actions for this table
     *
     * @param bool $exists Whether or not the table existed prior to executing this method
     * @return void
     */
    protected function executeActions($exists)
    {
        // Renaming a table is tricky, specially when running a reversible migration
        // down. We will just assume the table already exists if the user commands a
        // table rename.
        $renamed = collection($this->actions->getActions())
            ->filter(function ($action) {
                return $action instanceof RenameTable;
            })
            ->first();

        if ($renamed) {
            $exists = true;
        }

        // If the table does not exist, the last command in the chain needs to be
        // a CreateTable action.
        if (!$exists) {
            $this->actions->addAction(new CreateTable($this->table));
        }

        $plan = new Plan($this->actions);
        $plan->execute($this->getAdapter());
    }
}
