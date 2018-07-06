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

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class AddColumn extends Action
{

    /**
     * The column to add
     *
     * @var Column
     */
    protected $column;

    /**
     * Constructor
     *
     * @param Table $table The table to add the column to
     * @param Column $column The column to add
     */
    public function __construct(Table $table, Column $column)
    {
        parent::__construct($table);
        $this->column = $column;
    }

    /**
     * Returns a new AddColumn object after assembling the given commands
     *
     * @param Table $table The table to add the column to
     * @param mixed $columnName The column name
     * @param mixed $type The column type
     * @param mixed $options The column options
     * @return AddColumn
     */
    public static function build(Table $table, $columnName, $type = null, $options = [])
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options); // map options to column methods

        return new static($table, $column);
    }

    /**
     * Returns the column to be added
     *
     * @return Column
     */
    public function getColumn()
    {
        return $this->column;
    }
}
