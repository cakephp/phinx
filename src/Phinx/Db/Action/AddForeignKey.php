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

class AddForeignKey extends Action
{

    /**
     * The foreign key to add
     *
     * @var ForeignKey
     */
    protected $foreignKey;

    /**
     * Constructor
     *
     * @param Table $table The table to add the foreign key to
     * @param ForeignKey $fk The foreign key to add
     */
    public function __construct(Table $table, ForeignKey $fk)
    {
        parent::__construct($table);
        $this->foreignKey = $fk;
    }

    /**
     * Creates a new AddForeignKey object after building the foreign key with
     * the passed attributes
     *
     * @param Table $table The table object to add the foreign key to
     * @param string|string[] $columns The columns for the foreign key
     * @param Table|string $referencedTable The table the foreign key references
     * @param string|array $referencedColumns The columns in the referenced table
     * @param array $options Extra options for the foreign key
     * @param string|null $name The name of the foreign key
     * @return AddForeignKey
     */
    public static function build(Table $table, $columns, $referencedTable, $referencedColumns = ['id'], array $options = [], $name = null)
    {
        if (is_string($referencedColumns)) {
            $referencedColumns = [$referencedColumns]; // str to array
        }

        if (is_string($referencedTable)) {
            $referencedTable = new Table($referencedTable);
        }

        $fk = new ForeignKey();
        $fk->setReferencedTable($referencedTable)
           ->setColumns($columns)
           ->setReferencedColumns($referencedColumns)
           ->setOptions($options);

        if ($name !== null) {
            $fk->setConstraint($name);
        }

        return new static($table, $fk);
    }

    /**
     * Returns the foreign key to be added
     *
     * @return ForeignKey
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}
