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

class RenameColumn extends Action
{

    /**
     * The column to be renamed
     *
     * @var Column
     */
    protected $column;

    /**
     * The new name for the column
     *
     * @var string
     */
    protected $newName;

    /**
     * Constructor
     *
     * @param Table $table The table where the column is
     * @param Column $column The column to be renamed
     * @param mixed $newName The new name for the column
     */
    public function __construct(Table $table, Column $column, $newName)
    {
        parent::__construct($table);
        $this->newName = $newName;
        $this->column = $column;
    }

    /**
     * Creates a new RenameColumn object after building the passed
     * arguments
     *
     * @param Table $table The table where the column is
     * @param mixed $columnName The name of the column to be changed
     * @param mixed $newName The new name for the column
     * @return RenameColumn
     */
    public static function build(Table $table, $columnName, $newName)
    {
        $column = new Column();
        $column->setName($columnName);

        return new static($table, $column, $newName);
    }

    /**
     * Returns the column to be changed
     *
     * @return Column
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * Returns the new name for the column
     *
     * @return string
     */
    public function getNewName()
    {
        return $this->newName;
    }
}
