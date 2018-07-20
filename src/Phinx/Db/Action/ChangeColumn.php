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

class ChangeColumn extends Action
{
    /**
     * The column definition
     *
     * @var Column
     */
    protected $column;

    /**
     * The name of the column to be changed
     *
     * @var string
     */
    protected $columnName;

    /**
     * Constructor
     *
     * @param Table $table The table to alter
     * @param mixed $columnName The name fo the column to change
     * @param Column $column The column definition
     */
    public function __construct(Table $table, $columnName, Column $column)
    {
        parent::__construct($table);
        $this->columnName = $columnName;
        $this->column = $column;

        // if the name was omitted use the existing column name
        if ($column->getName() === null || strlen($column->getName()) === 0) {
            $column->setName($columnName);
        }
    }

    /**
     * Creates a new ChangeColumn object after building the column definition
     * out of the provided arguments
     *
     * @param Table $table The table to alter
     * @param mixed $columnName The name of the column to change
     * @param mixed $type The type of the column
     * @param mixed $options Additional options for the column
     * @return ChangeColumn
     */
    public static function build(Table $table, $columnName, $type = null, $options = [])
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options); // map options to column methods

        return new static($table, $columnName, $column);
    }

    /**
     * Returns the name of the column to change
     *
     * @return string
     */
    public function getColumnName()
    {
        return $this->columnName;
    }

    /**
     * Returns the column definition
     *
     * @return Column
     */
    public function getColumn()
    {
        return $this->column;
    }
}
