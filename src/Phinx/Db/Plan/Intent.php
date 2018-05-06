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

use Phinx\Db\Action\Action;

/**
 * An intent is a collection of actions for many tables
 *
 */
class Intent
{

    /**
     * List of actions to be executed
     *
     * @var \Phinx\Db\Action\Action[]
     */
    protected $actions = [];

    /**
     * Adds a new action to the collection
     *
     * @param Action $action The action to add
     * @return void
     */
    public function addAction(Action $action)
    {
        $this->actions[] = $action;
    }

    /**
     * Returns the full list of actions
     *
     * @return Action[]
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Merges another Intent object with this one
     *
     * @param Intent $another The other intent to merge in
     * @return void
     */
    public function merge(Intent $another)
    {
        $this->actions = array_merge($this->actions, $another->getActions());
    }
}
