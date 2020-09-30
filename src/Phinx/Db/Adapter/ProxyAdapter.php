<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
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
     * @var \Phinx\Db\Action\Action[]
     */
    protected $commands = [];

    /**
     * @inheritDoc
     */
    public function getAdapterType()
    {
        return 'ProxyAdapter';
    }

    /**
     * @inheritDoc
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $this->commands[] = new CreateTable($table);
    }

    /**
     * @inheritDoc
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

        foreach (array_reverse($this->commands) as $command) {
            switch (true) {
                case $command instanceof CreateTable:
                    $inverted->addAction(new DropTable($command->getTable()));
                    break;

                case $command instanceof RenameTable:
                    $inverted->addAction(new RenameTable(new Table($command->getNewName()), $command->getTable()->getName()));
                    break;

                case $command instanceof AddColumn:
                    $inverted->addAction(new RemoveColumn($command->getTable(), $command->getColumn()));
                    break;

                case $command instanceof RenameColumn:
                    $column = clone $command->getColumn();
                    $name = $column->getName();
                    $column->setName($command->getNewName());
                    $inverted->addAction(new RenameColumn($command->getTable(), $column, $name));
                    break;

                case $command instanceof AddIndex:
                    $inverted->addAction(new DropIndex($command->getTable(), $command->getIndex()));
                    break;

                case $command instanceof AddForeignKey:
                    $inverted->addAction(new DropForeignKey($command->getTable(), $command->getForeignKey()));
                    break;

                default:
                    throw new IrreversibleMigrationException(sprintf(
                        'Cannot reverse a "%s" command',
                        get_class($command)
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
