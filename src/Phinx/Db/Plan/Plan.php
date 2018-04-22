<?php

namespace Phinx\Db\Plan;

use Phinx\Db\Action\AddColumn;
use Phinx\Db\Action\AddForeignKey;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\ChangeColumn;
use Phinx\Db\Action\CreateTable;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table\Table;

class Plan
{

    protected $tableCreates = [];

    protected $tableUpdates = [];

    protected $tableMoves = [];

    protected $indexes = [];

    protected $constraints = [];

    public function __construct(Intent $intent)
    {
        $this->createPlan($intent->getActions());
    }

    protected function createPlan($actions)
    {
        $this->gatherCreates($actions);
        $this->gatherUpdates($actions);
        $this->gatherTableMoves($actions);
        $this->gatherIndexes($actions);
        $this->gatherConstraints($actions);
        $this->resolveConflicts();
    }

    protected function updatesSequence()
    {
        return [
            $this->tableUpdates,
            $this->constraints,
            $this->indexes,
            $this->tableMoves,
        ];
    }

    public function execute(AdapterInterface $executor)
    {
        foreach ($this->tableCreates as $newTable) {
            $executor->createTable($newTable->getTable(), $newTable->getColumns(), $newTable->getIndexes());
        }

        collection($this->updatesSequence())
            ->unfold()
            ->each(function ($updates) use ($executor) {
                $executor->executeActions($updates->getTable(), $updates->getActions());
            });
    }

    public function executeInverse(AdapterInterface $executor)
    {
        collection(array_reverse($this->updatesSequence()))
            ->unfold()
            ->each(function ($updates) use ($executor) {
                $executor->executeActions($updates->getTable(), $updates->getActions());
            });

        foreach ($this->tableCreates as $newTable) {
            $executor->createTable($newTable->getTable(), $newTable->getColumns(), $newTable->getIndexes());
        }
    }

    protected function resolveConflicts()
    {
        $actions = collection($this->tableMoves)
            ->unfold(function ($move) {
                return $move->getActions();
            });

        foreach ($actions as $action) {
            if ($action instanceof DropTable) {
                $this->tableUpdates = $this->forgetActions($action->getTable(), $this->tableUpdates);
                $this->constraints = $this->forgetActions($action->getTable(), $this->constraints);
                $this->indexes = $this->forgetActions($action->getTable(), $this->indexes);
            }
        }
    }

    protected function forgetActions(Table $table, $actions)
    {
        $result = [];
        foreach ($actions as $action) {
            if ($action->getTable()->getName() === $table->getName()) {
                continue;
            }
            $result[] = $action;
        }

        return $result;
    }

    protected function gatherCreates($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof CreateTable;
            })
            ->map(function ($action) {
                $table = $action->getTable();
                return [$table->getName(), new NewTable($table)];
            })
            ->each(function ($step) {
                $this->tableCreates[$step[0]] = $step[1];
            });

        collection($actions)
            ->filter(function ($action) {
                return $action instanceof AddColumn
                    || $action instanceof AddIndex;
            })
            ->filter(function ($action) {
                return isset($this->tableCreates[$action->getTable()->getName()]);
            })
            ->each(function ($action) {
                $table = $action->getTable();

                if ($action instanceof AddColumn) {
                    $this->tableCreates[$table->getName()]->addColumn($action->getColumn());
                }

                if ($action instanceof AddIndex) {
                    $this->tableCreates[$table->getName()]->addIndex($action->getIndex());
                }
            });
    }

    protected function gatherUpdates($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof AddColumn
                    || $action instanceof ChangeColumn
                    || $action instanceof RemoveColumn
                    || $action instanceof RenameColumn;
            })
            // We are only concerned with table changes
            ->reject(function ($action) {
                return isset($this->tableCreates[$action->getTable()->getName()]);
            })
            ->each(function ($action) {
                $table = $action->getTable();
                $name = $table->getName();

                if (!isset($this->tableUpdates[$name])) {
                    $this->tableUpdates[$name] = new AlterTable($table);
                }

                $this->tableUpdates[$name]->addAction($action);
            });
    }

    protected function gatherTableMoves($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof DropTable
                    || $action instanceof RenameTable;
            })
            ->each(function ($action) {
                $table = $action->getTable();
                $name = $table->getName();

                if (!isset($this->tableMoves[$name])) {
                    $this->tableMoves[$name] = new AlterTable($table);
                }

                $this->tableMoves[$name]->addAction($action);
            });
    }

    protected function gatherIndexes($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof AddIndex
                    || $action instanceof DropIndex;
            })
            ->reject(function ($action) {
                // Indexes for new tables are created inline
                // so we don't wan't them here too
                return isset($this->tableCreates[$action->getTable()->getName()]);
            })
            ->each(function ($action) {
                $table = $action->getTable();
                $name = $table->getName();

                if (!isset($this->indexes[$name])) {
                    $this->indexes[$name] = new AlterTable($table);
                }

                $this->indexes[$name]->addAction($action);
            });
    }

    protected function gatherConstraints($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof AddForeignKey
                    || $action instanceof DropForeignKey;
            })
            ->each(function ($action) {
                $table = $action->getTable();
                $name = $table->getName();

                if (!isset($this->constraints[$name])) {
                    $this->constraints[$name] = new AlterTable($table);
                }

                $this->constraints[$name]->addAction($action);
            });
    }
}
