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
namespace Phinx\Db\Plan\Solver;

use Phinx\Db\Plan\AlterTable;

/**
 * A Plan takes an Intent and transforms int into a sequence of
 * instructions that can be correctly executed by an AdapterInterface.
 *
 * The main focus of Plan is to arrange the actions in the most efficient
 * way possible for the database.
 */
class ActionSplitter
{
    /**
     * The fully qualified class name of the Action class to match for conflicts
     * @var string
     */
    protected $conflictClass;

    /**
     * The fully qualified class name of the Action class to match for conflicts, which
     * is the dual of $conflictClass. For example `AddColumn` and `DropColumn` are duals.
     * @var string
     */
    protected $conflictClassDual;

    /**
     * A callback used to signal the actual presence of a conflict, that will be used to
     * partition the AlterTable into non-conflicting parts.
     *
     * The callback receives as first argument amn instance of $conflictClass and as second
     * argument an instance of $conflictClassDual
     *
     * @var callable
     */
    protected $conflictFilter;

    /**
     * Comstructor
     *
     * @param string $conflictClass The fully qualified class name of the Action class to match for conflicts
     * @param string $conflictClassDual The fully qualified class name of the Action class to match for conflicts,
     * which is the dual of $conflictClass. For example `AddColumn` and `DropColumn` are duals.
     * @param callable $conflictFilter The collection of actions to inspect
     */
    public function __construct($conflictClass, $conflictClassDual, callable $conflictFilter)
    {
        $this->conflictClass = $conflictClass;
        $this->conflictClassDual = $conflictClassDual;
        $this->conflictFilter = $conflictFilter;
    }

    /**
     * Returs a sequence of AlterTable instructions that are non conflicting
     * based on the constructor parameters.
     *
     * @param AlterTable $alter The collection of actions to inspect
     * @return AlterTable[] A list of AlterTable that can be executed without
     * this type of conflict
     */
    public function __invoke(AlterTable $alter)
    {
        $actions = collection($alter->getActions());
        $conflictActions = $actions
            ->filter(function ($action) {
                return $action instanceof $this->conflictClass;
            })
            ->toList();

        $originalAlter = new AlterTable($alter->getTable());
        $newAlter = new AlterTable($alter->getTable());

        $actions
            ->map(function ($action) use ($conflictActions) {
                if (!$action instanceof $this->conflictClassDual) {
                    return [$action, null];
                }

                $found = false;
                $matches = $this->conflictFilter;
                foreach ($conflictActions as $ca) {
                    if ($matches($ca, $action)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    return [null, $action];
                }

                return [$action, null];
            })
            ->each(function ($pair) use ($originalAlter, $newAlter) {
                list($original, $new) = $pair;
                if ($original) {
                    $originalAlter->addAction($original);
                }
                if ($new) {
                    $newAlter->addAction($new);
                }
            });

        return [$originalAlter, $newAlter];
    }
}
