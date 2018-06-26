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

use Phinx\Db\Action\AddColumn;
use Phinx\Db\Action\AddForeignKey;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\CreateTable;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Plan\Intent;
use Phinx\Db\Plan\Plan;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Migration\IrreversibleMigrationException;

/**
 * Phinx Proxy Adapter.
 *
 * Used for recording migration commands to automatically reverse them.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
class ProxyAdapter extends AdapterWrapper
{
    /**
     * @var array
     */
    protected $commands = [];

    /**
     * {@inheritdoc}
     */
    public function getAdapterType()
    {
        return 'ProxyAdapter';
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $this->commands[] = new CreateTable($table);
    }

    /**
     * {@inheritdoc}
     */
    public function executeActions(Table $table, array $actions)
    {
        $this->commands = array_merge($this->commands, $actions);
    }

    /**
     * Gets an array of the recorded commands in reverse.
     *
     * @throws \Phinx\Migration\IrreversibleMigrationException if a command cannot be reversed.
     * @return \Phinx\Db\Plan\Intent
     */
    public function getInvertedCommands()
    {
        $inverted = new Intent();

        foreach (array_reverse($this->commands) as $com) {
            switch (true) {
                case $com instanceof CreateTable:
                    $inverted->addAction(new DropTable($com->getTable()));
                    break;

                case $com instanceof RenameTable:
                    $inverted->addAction(new RenameTable(new Table($com->getNewName()), $com->getTable()->getName()));
                    break;

                case $com instanceof AddColumn:
                    $inverted->addAction(new RemoveColumn($com->getTable(), $com->getColumn()));
                    break;

                case $com instanceof RenameColumn:
                    $column = clone $com->getColumn();
                    $name = $column->getName();
                    $column->setName($com->getNewName());
                    $inverted->addAction(new RenameColumn($com->getTable(), $column, $name));
                    break;

                case $com instanceof AddIndex:
                    $inverted->addAction(new DropIndex($com->getTable(), $com->getIndex()));
                    break;

                case $com instanceof AddForeignKey:
                    $inverted->addAction(new DropForeignKey($com->getTable(), $com->getForeignKey()));
                    break;

                default:
                    throw new IrreversibleMigrationException(sprintf(
                        'Cannot reverse a "%s" command',
                        get_class($com)
                    ));
            }
        }

        return $inverted;
    }

    /**
     * Execute the recorded commands in reverse.
     *
     * @return void
     */
    public function executeInvertedCommands()
    {
        $plan = new Plan($this->getInvertedCommands());
        $plan->executeInverse($this->getAdapter());
    }

    /**
     * Renames the specified database table.
     *
     * @param string $tableName Table Name
     * @param string $newName New Name
     * @return void
     */
    public function renameTable($tableName, $newName)
    {
        // TODO: Implement renameTable() method.
    }

    /**
     * Drops the specified database table.
     *
     * @param string $tableName Table Name
     * @return void
     */
    public function dropTable($tableName)
    {
        // TODO: Implement dropTable() method.
    }

    /**
     * Adds the specified column to a database table.
     *
     * @param \Phinx\Db\Table $table Table
     * @param Column $column Column
     * @return void
     */
    public function addColumn(\Phinx\Db\Table $table, Column $column)
    {
        // TODO: Implement addColumn() method.
    }

    /**
     * Renames the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return void
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        // TODO: Implement renameColumn() method.
    }

    /**
     * Change a table column type.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param Column $newColumn New Column
     * @return \Phinx\Db\Table
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        // TODO: Implement changeColumn() method.
    }

    /**
     * Drops the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @return void
     */
    public function dropColumn($tableName, $columnName)
    {
        // TODO: Implement dropColumn() method.
    }

    /**
     * Adds the specified index to a database table.
     *
     * @param \Phinx\Db\Table $table Table
     * @param Index $index Index
     * @return void
     */
    public function addIndex(\Phinx\Db\Table $table, Index $index)
    {
        // TODO: Implement addIndex() method.
    }

    /**
     * Drops the specified index from a database table.
     *
     * @param string $tableName
     * @param mixed $columns Column(s)
     * @return void
     */
    public function dropIndex($tableName, $columns)
    {
        // TODO: Implement dropIndex() method.
    }

    /**
     * Drops the index specified by name from a database table.
     *
     * @param string $tableName
     * @param string $indexName
     * @return void
     */
    public function dropIndexByName($tableName, $indexName)
    {
        // TODO: Implement dropIndexByName() method.
    }

    /**
     * Adds the specified foreign key to a database table.
     *
     * @param \Phinx\Db\Table $table
     * @param ForeignKey $foreignKey
     * @return void
     */
    public function addForeignKey(\Phinx\Db\Table $table, ForeignKey $foreignKey)
    {
        // TODO: Implement addForeignKey() method.
    }

    /**
     * Drops the specified foreign key from a database table.
     *
     * @param string $tableName
     * @param string[] $columns Column(s)
     * @param string $constraint Constraint name
     * @return void
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        // TODO: Implement dropForeignKey() method.
    }
}
