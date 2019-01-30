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

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;

/**
 * Represents the collection of actions for creating a new table
 *
 */
class NewTable
{
    /**
     * The table to create
     *
     * @var \Phinx\Db\Table\Table
     */
    protected $table;

    /**
     * The list of columns to add
     *
     * @var \Phinx\Db\Table\Column[]
     */
    protected $columns = [];

    /**
     * The list of indexes to create
     *
     * @var \Phinx\Db\Table\Index[]
     */
    protected $indexes = [];

    /**
     * Constructor
     *
     * @param Table $table The table to create
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * Adds a column to the collection
     *
     * @param Column $column The column description
     * @return void
     */
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;
    }

    /**
     * Adds an index to the collection
     *
     * @param Index $index The index description
     * @return void
     */
    public function addIndex(Index $index)
    {
        $this->indexes[] = $index;
    }

    /**
     * Returns the table object associated to this collection
     *
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns the columns collection
     *
     * @return Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Returns the indexes collection
     *
     * @return Index[]
     */
    public function getIndexes()
    {
        return $this->indexes;
    }
}
