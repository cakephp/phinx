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
use Phinx\Db\Action\ChangeColumn;
use Phinx\Db\Action\ChangeComment;
use Phinx\Db\Action\ChangePrimaryKey;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;

/**
 * A generic implementation of DirectActionInterface
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
trait AbstractDirectAction
{
    /**
     * Executes all the ALTER TABLE instructions passed for the given table
     *
     * @param string $tableName The table name to use in the ALTER statement
     * @param AlterInstructions $instructions The object containing the alter sequence
     * @return void
     */
    abstract protected function executeAlterSteps($tableName, AlterInstructions $instructions);

    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $instructions = $this->getAddColumnInstructions($table, $column);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified column to a database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Column $column Column
     * @return AlterInstructions
     */
    abstract protected function getAddColumnInstructions(Table $table, Column $column);

    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $instructions = $this->getRenameColumnInstructions($tableName, $columnName, $newColumnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return AlterInstructions
     *
     */
    abstract protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName);

    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $instructions = $this->getChangeColumnInstructions($tableName, $columnName, $newColumn);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to change a table column type.
     *
     * @param string $tableName  Table Name
     * @param string $columnName Column Name
     * @param \Phinx\Db\Table\Column $newColumn  New Column
     * @return AlterInstructions
     */
    abstract protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn);

    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $instructions = $this->getDropColumnInstructions($tableName, $columnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @return AlterInstructions
     */
    abstract protected function getDropColumnInstructions($tableName, $columnName);

    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        $instructions = $this->getAddIndexInstructions($table, $index);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified index to a database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Index $index Index
     * @return AlterInstructions
     */
    abstract protected function getAddIndexInstructions(Table $table, Index $index);

    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        $instructions = $this->getDropIndexByColumnsInstructions($tableName, $columns);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified index from a database table.
     *
     * @param string $tableName The name of of the table where the index is
     * @param mixed $columns Column(s)
     * @return AlterInstructions
     */
    abstract protected function getDropIndexByColumnsInstructions($tableName, $columns);

    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $instructions = $this->getDropIndexByNameInstructions($tableName, $indexName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the index specified by name from a database table.
     *
     * @param string $tableName The table name whe the index is
     * @param string $indexName The name of the index
     * @return AlterInstructions
     */
    abstract protected function getDropIndexByNameInstructions($tableName, $indexName);

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $instructions = $this->getAddForeignKeyInstructions($table, $foreignKey);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to adds the specified foreign key to a database table.
     *
     * @param \Phinx\Db\Table\Table $table The table to add the constraint to
     * @param \Phinx\Db\Table\ForeignKey $foreignKey The foreign key to add
     * @return AlterInstructions
     */
    abstract protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey);

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        if ($constraint) {
            $instructions = $this->getDropForeignKeyInstructions($tableName, $constraint);
        } else {
            $instructions = $this->getDropForeignKeyByColumnsInstructions($tableName, $columns);
        }

        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string   $tableName The table where the foreign key constraint is
     * @param string   $constraint Constraint name
     * @return AlterInstructions
     */
    abstract protected function getDropForeignKeyInstructions($tableName, $constraint);

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param array $columns The list of column names
     * @return AlterInstructions
     */
    abstract protected function getDropForeignKeyByColumnsInstructions($tableName, $columns);

    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $instructions = $this->getDropTableInstructions($tableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified database table.
     *
     * @param string $tableName Table Name
     * @return AlterInstructions
     */
    abstract protected function getDropTableInstructions($tableName);

    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $instructions = $this->getRenameTableInstructions($tableName, $newTableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified database table.
     *
     * @param string $tableName Table Name
     * @param string $newTableName New Name
     * @return AlterInstructions
     */
    abstract protected function getRenameTableInstructions($tableName, $newTableName);

    /**
     * {@inheritdoc}
     */
    public function changePrimaryKey(Table $table, $newColumns)
    {
        $instructions = $this->getChangePrimaryKeyInstructions($table, $newColumns);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to change the primary key for the specified database table.
     *
     * @param Table $table Table
     * @param string|array|null $newColumns Column name(s) to belong to the primary key, or null to drop the key
     * @return AlterInstructions
     */
    abstract protected function getChangePrimaryKeyInstructions(Table $table, $newColumns);

    /**
     * {@inheritdoc}
     */
    public function changeComment(Table $table, $newComment)
    {
        $instructions = $this->getChangeCommentInstructions($table, $newComment);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instruction to change the comment for the specified database table.
     *
     * @param Table $table Table
     * @param string|null $newComment New comment string, or null to drop the comment
     * @return AlterInstructions
     */
    abstract protected function getChangeCommentInstructions(Table $table, $newComment);

    /**
     * {@inheritdoc}
     */
    public function executeActions(Table $table, array $actions)
    {
        $instructions = new AlterInstructions();

        foreach ($actions as $action) {
            switch (true) {
                case ($action instanceof AddColumn):
                    $instructions->merge($this->getAddColumnInstructions($table, $action->getColumn()));
                    break;

                case ($action instanceof AddIndex):
                    $instructions->merge($this->getAddIndexInstructions($table, $action->getIndex()));
                    break;

                case ($action instanceof AddForeignKey):
                    $instructions->merge($this->getAddForeignKeyInstructions($table, $action->getForeignKey()));
                    break;

                case ($action instanceof ChangeColumn):
                    $instructions->merge($this->getChangeColumnInstructions(
                        $table->getName(),
                        $action->getColumnName(),
                        $action->getColumn()
                    ));
                    break;

                case ($action instanceof DropForeignKey && !$action->getForeignKey()->getConstraint()):
                    $instructions->merge($this->getDropForeignKeyByColumnsInstructions(
                        $table->getName(),
                        $action->getForeignKey()->getColumns()
                    ));
                    break;

                case ($action instanceof DropForeignKey && $action->getForeignKey()->getConstraint()):
                    $instructions->merge($this->getDropForeignKeyInstructions(
                        $table->getName(),
                        $action->getForeignKey()->getConstraint()
                    ));
                    break;

                case ($action instanceof DropIndex && $action->getIndex()->getName() !== null):
                    $instructions->merge($this->getDropIndexByNameInstructions(
                        $table->getName(),
                        $action->getIndex()->getName()
                    ));
                    break;

                case ($action instanceof DropIndex && $action->getIndex()->getName() == null):
                    $instructions->merge($this->getDropIndexByColumnsInstructions(
                        $table->getName(),
                        $action->getIndex()->getColumns()
                    ));
                    break;

                case ($action instanceof DropTable):
                    $instructions->merge($this->getDropTableInstructions(
                        $table->getName()
                    ));
                    break;

                case ($action instanceof RemoveColumn):
                    $instructions->merge($this->getDropColumnInstructions(
                        $table->getName(),
                        $action->getColumn()->getName()
                    ));
                    break;

                case ($action instanceof RenameColumn):
                    $instructions->merge($this->getRenameColumnInstructions(
                        $table->getName(),
                        $action->getColumn()->getName(),
                        $action->getNewName()
                    ));
                    break;

                case ($action instanceof RenameTable):
                    $instructions->merge($this->getRenameTableInstructions(
                        $table->getName(),
                        $action->getNewName()
                    ));
                    break;

                case ($action instanceof ChangePrimaryKey):
                    $instructions->merge($this->getChangePrimaryKeyInstructions(
                        $table,
                        $action->getNewColumns()
                    ));
                    break;

                case ($action instanceof ChangeComment):
                    $instructions->merge($this->getChangeCommentInstructions(
                        $table,
                        $action->getNewComment()
                    ));
                    break;

                default:
                    throw new \InvalidArgumentException(
                        sprintf("Don't know how to execute action: '%s'", get_class($action))
                    );
            }
        }

        $this->executeAlterSteps($table->getName(), $instructions);
    }
}
