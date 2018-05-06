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
}
