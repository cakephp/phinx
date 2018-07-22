<?php
/**
 * Phinx
 *
 * (The MIT license)
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
 */
namespace Phinx\Db\Plan;

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
use Phinx\Db\Table\Table;

/**
 * A Plan takes an Intent and transforms int into a sequence of
 * instructions that can be correctly executed by an AdapterInterface.
 *
 * The main focus of Plan is to arrange the actions in the most efficient
 * way possible for the database.
 */
class Plan
{

    /**
     * List of tables to be created
     *
     * @var \Phinx\Db\Plan\NewTable[]
     */
    protected $tableCreates = [];

    /**
     * List of table updates
     *
     * @var \Phinx\Db\Plan\AlterTable[]
     */
    protected $tableUpdates = [];

    /**
     * List of table removals or renames
     *
     * @var \Phinx\Db\Plan\AlterTable[]
     */
    protected $tableMoves = [];

    /**
     * List of index additions or removals
     *
     * @var \Phinx\Db\Plan\AlterTable[]
     */
    protected $indexes = [];

    /**
     * List of constraint additions or removals
     *
     * @var \Phinx\Db\Plan\AlterTable[]
     */
    protected $constraints = [];

    /**
     * Constructor
     *
     * @param Intent $intent All the actions that should be executed
     */
    public function __construct(Intent $intent)
    {
        $this->createPlan($intent->getActions());
    }

    /**
     * Parses the given Intent and creates the separate steps to execute
     *
     * @param Intent $actions The actions to use for the plan
     * @return void
     */
    protected function createPlan($actions)
    {
        $this->gatherCreates($actions);
        $this->gatherUpdates($actions);
        $this->gatherTableMoves($actions);
        $this->gatherIndexes($actions);
        $this->gatherConstraints($actions);
        $this->resolveConflicts();
    }

    /**
     * Returns a nested list of all the steps to execute
     *
     * @return AlterTable[][]
     */
    protected function updatesSequence()
    {
        return [
            $this->tableUpdates,
            $this->constraints,
            $this->indexes,
            $this->tableMoves,
        ];
    }

    /**
     * Executes this plan using the given AdapterInterface
     *
     * @param AdapterInterface $executor The executor object for the plan
     * @return void
     */
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

    /**
     * Executes the inverse plan (rollback the actions) with the given AdapterInterface:w
     *
     * @param AdapterInterface $executor The executor object for the plan
     * @return void
     */
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

    /**
     * Deletes certain actions from the plan if they are found to be conflicting or redundant.
     *
     * @return void
     */
    protected function resolveConflicts()
    {
        $actions = collection($this->tableMoves)
            ->unfold(function ($move) {
                return $move->getActions();
            });

        foreach ($actions as $action) {
            if ($action instanceof DropTable) {
                $this->tableUpdates = $this->forgetTable($action->getTable(), $this->tableUpdates);
                $this->constraints = $this->forgetTable($action->getTable(), $this->constraints);
                $this->indexes = $this->forgetTable($action->getTable(), $this->indexes);
            }
        }
    }

    /**
     * Deletes all actions related to the given table and keeps the
     * rest
     *
     * @param Table $table The table to find in the list of actions
     * @param AlterTable[] $actions The actions to transform
     * @return AlterTable[] The list of actions without actions for the given table
     */
    protected function forgetTable(Table $table, $actions)
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

    /**
     * Collects all table creation actions from the given intent
     *
     * @param \Phinx\Db\Action\Action[] $actions The actions to parse
     * @return void
     */
    protected function gatherCreates($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof CreateTable;
            })
            ->map(function ($action) {
                return [$action->getTable()->getName(), new NewTable($action->getTable())];
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

    /**
     * Collects all alter table actions from the given intent
     *
     * @param \Phinx\Db\Action\Action[] $actions The actions to parse
     * @return void
     */
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

    /**
     * Collects all alter table drop and renames from the given intent
     *
     * @param \Phinx\Db\Action\Action[] $actions The actions to parse
     * @return void
     */
    protected function gatherTableMoves($actions)
    {
        collection($actions)
            ->filter(function ($action) {
                return $action instanceof DropTable
                    || $action instanceof RenameTable
                    || $action instanceof ChangePrimaryKey
                    || $action instanceof ChangeComment;
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

    /**
     * Collects all index creation and drops from the given intent
     *
     * @param \Phinx\Db\Action\Action[] $actions The actions to parse
     * @return void
     */
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

    /**
     * Collects all foreign key creation and drops from the given intent
     *
     * @param \Phinx\Db\Action\Action[] $actions The actions to parse
     * @return void
     */
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
