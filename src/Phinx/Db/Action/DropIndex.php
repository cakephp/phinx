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
namespace Phinx\Db\Action;

use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;

class DropIndex extends Action
{

    /**
     * The index to drop
     *
     * @var Index
     */
    protected $index;

    /**
     * Constructor
     *
     * @param Table $table The table owning the index
     * @param Index $index The index to be dropped
     */
    public function __construct(Table $table, Index $index)
    {
        parent::__construct($table);
        $this->index = $index;
    }

    /**
     * Creates a new DropIndex object after assembling the passed
     * arguments.
     *
     * @param Table $table The table where the index is
     * @param array $columns the indexed columns
     * @return DropIndex
     */
    public static function build(Table $table, array $columns = [])
    {
        $index = new Index();
        $index->setColumns($columns);

        return new static($table, $index);
    }

    /**
     * Creates a new DropIndex when the name of the index to drop
     * is known.
     *
     * @param Table $table The table where the index is
     * @param mixed $name The name of the index
     * @return DropIndex
     */
    public static function buildFromName(Table $table, $name)
    {
        $index = new Index();
        $index->setName($name);

        return new static($table, $index);
    }

    /**
     * Returns the index to be dropped
     *
     * @return Index
     */
    public function getIndex()
    {
        return $this->index;
    }
}
