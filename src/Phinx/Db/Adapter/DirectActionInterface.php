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
namespace Phinx\Db\Adapter;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;

/**
 * Represents an adapter that is capable of directly executing alter
 * instructions, without having to plan them first.
 *
 */
interface DirectActionInterface
{
    /**
     * Renames the specified database table.
     *
     * @param string $tableName Table Name
     * @param string $newName   New Name
     * @return void
     */
    public function renameTable($tableName, $newName);

    /**
     * Drops the specified database table.
     *
     * @param string $tableName Table Name
     * @return void
     */
    public function dropTable($tableName);

    /**
     * Changes the primary key of the specified database table.
     *
     * @param Table $table Table
     * @param string|array|null $newColumns Column name(s) to belong to the primary key, or null to drop the key
     * @return void
     */
    public function changePrimaryKey(Table $table, $newColumns);

    /**
     * Changes the comment of the specified database table.
     *
     * @param Table $table Table
     * @param string|null $newComment New comment string, or null to drop the comment
     * @return void
     */
    public function changeComment(Table $table, $newComment);

    /**
     * Adds the specified column to a database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Column $column Column
     * @return void
     */
    public function addColumn(Table $table, Column $column);

    /**
     * Renames the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return void
     */
    public function renameColumn($tableName, $columnName, $newColumnName);

    /**
     * Change a table column type.
     *
     * @param string $tableName  Table Name
     * @param string $columnName Column Name
     * @param \Phinx\Db\Table\Column $newColumn  New Column
     * @return void
     */
    public function changeColumn($tableName, $columnName, Column $newColumn);

    /**
     * Drops the specified column.
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     * @return void
     */
    public function dropColumn($tableName, $columnName);

    /**
     * Adds the specified index to a database table.
     *
     * @param \Phinx\Db\Table\Table $table Table
     * @param \Phinx\Db\Table\Index $index Index
     * @return void
     */
    public function addIndex(Table $table, Index $index);

    /**
     * Drops the specified index from a database table.
     *
     * @param string $tableName the name of the table
     * @param mixed  $columns Column(s)
     * @return void
     */
    public function dropIndex($tableName, $columns);

    /**
     * Drops the index specified by name from a database table.
     *
     * @param string $tableName The table name where the index is
     * @param string $indexName The name of the index
     * @return void
     */
    public function dropIndexByName($tableName, $indexName);

    /**
     * Adds the specified foreign key to a database table.
     *
     * @param \Phinx\Db\Table\Table      $table The table to add the foreign key to
     * @param \Phinx\Db\Table\ForeignKey $foreignKey The foreign key to add
     * @return void
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey);

    /**
     * Drops the specified foreign key from a database table.
     *
     * @param string $tableName The table to drop the foreign key from
     * @param string[] $columns Column(s)
     * @param string|null $constraint Constraint name
     * @return void
     */
    public function dropForeignKey($tableName, $columns, $constraint = null);
}
