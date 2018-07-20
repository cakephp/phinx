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

use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Table;

class DropForeignKey extends Action
{

    /**
     * The foreign key to remove
     *
     * @var ForeignKey
     */
    protected $foreignKey;

    /**
     * Constructor
     *
     * @param Table $table The table to remove the constraint from
     * @param ForeignKey $foreignKey The foreign key to remove
     */
    public function __construct(Table $table, ForeignKey $foreignKey)
    {
        parent::__construct($table);
        $this->foreignKey = $foreignKey;
    }

    /**
     * Creates a new DropForeignKey object after building the ForeignKey
     * definition out of the passed arguments.
     *
     * @param Table $table The table to delete the foreign key from
     * @param string|string[] $columns The columns participating in the foreign key
     * @param string|null $constraint The constraint name
     * @return DropForeignKey
     */
    public static function build(Table $table, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $foreignKey = new ForeignKey();
        $foreignKey->setColumns($columns);

        if ($constraint) {
            $foreignKey->setConstraint($constraint);
        }

        return new static($table, $foreignKey);
    }

    /**
     * Returns the  foreign key to remove
     *
     * @return ForeignKey
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}
