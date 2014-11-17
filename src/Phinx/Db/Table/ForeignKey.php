<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
 *
 * @package    Phinx
 * @subpackage Phinx\Db
 * @author     Leonid Kuzmin <lndkuzmin@gmail.com>
 */
namespace Phinx\Db\Table;

use Phinx\Db\Table;

class ForeignKey
{
    const CASCADE = 'CASCADE';
    const RESTRICT = 'RESTRICT';
    const SET_NULL = 'SET NULL';
    const NO_ACTION = 'NO ACTION';

    /**
     * @var array
     */
    protected $columns = array();

    /**
     * @var Table
     */
    protected $referencedTable;

    /**
     * @var array
     */
    protected $referencedColumns = array();

    /**
     * @var string
     */
    protected $onDelete;

    /**
     * @var string
     */
    protected $onUpdate;

    /**
     * @var string|boolean
     */
    protected $constraint;

    /**
     * Sets the foreign key columns.
     *
     * @param array|string $columns
     * @return ForeignKey
     */
    public function setColumns($columns)
    {
        if (is_string($columns)) {
            $columns = array($columns);
        }
        $this->columns = $columns;
        return $this;
    }

    /**
     * Gets the foreign key columns.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Sets the foreign key referenced table.
     *
     * @param Table $table
     * @return ForeignKey
     */
    public function setReferencedTable(Table $table)
    {
        $this->referencedTable = $table;
        return $this;
    }

    /**
     * Gets the foreign key referenced table.
     *
     * @return Table
     */
    public function getReferencedTable()
    {
        return $this->referencedTable;
    }

    /**
     * Sets the foreign key referenced columns.
     *
     * @param array $referencedColumns
     * @return ForeignKey
     */
    public function setReferencedColumns(array $referencedColumns)
    {
        $this->referencedColumns = $referencedColumns;
        return $this;
    }

    /**
     * Gets the foreign key referenced columns.
     *
     * @return array
     */
    public function getReferencedColumns()
    {
        return $this->referencedColumns;
    }

    /**
     * Sets ON DELETE action for the foreign key.
     *
     * @param string $onDelete
     * @return ForeignKey
     */
    public function setOnDelete($onDelete)
    {
        $this->onDelete = $this->normalizeAction($onDelete);
        return $this;
    }

    /**
     * Gets ON DELETE action for the foreign key.
     *
     * @return string
     */
    public function getOnDelete()
    {
        return $this->onDelete;
    }

    /**
     * Gets ON UPDATE action for the foreign key.
     *
     * @return string
     */
    public function getOnUpdate()
    {
        return $this->onUpdate;
    }

    /**
     * Sets ON UPDATE action for the foreign key.
     *
     * @param string $onUpdate
     * @return ForeignKey
     */
    public function setOnUpdate($onUpdate)
    {
        $this->onUpdate = $this->normalizeAction($onUpdate);
        return $this;
    }

    /**
     * Sets constraint for the foreign key.
     *
     * @param string $constraint
     * @return ForeignKey
     */
    public function setConstraint($constraint)
    {
        $this->constraint = $constraint;
        return $this;
    }

    /**
     * Gets constraint name for the foreign key.
     *
     * @return string
     */
    public function getConstraint()
    {
        return $this->constraint;
    }

    /**
     * Utility method that maps an array of index options to this objects methods.
     *
     * @param array $options Options
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return ForeignKey
     */
    public function setOptions($options)
    {
        // Valid Options
        $validOptions = array('delete', 'update', 'constraint');
        foreach ($options as $option => $value) {
            if (!in_array($option, $validOptions)) {
                throw new \RuntimeException('\'' . $option . '\' is not a valid foreign key option.');
            }

            // handle $options['delete'] as $options['update']
            if ('delete' == $option) {
                $this->setOnDelete($value);
            } elseif ('update' == $option) {
                $this->setOnUpdate($value);
            } else {
                $method = 'set' . ucfirst($option);
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * From passed value checks if it's correct and fixes if needed
     *
     * @param string $action
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function normalizeAction($action)
    {
        $constantName = 'static::' . str_replace(' ', '_', strtoupper(trim($action)));
        if (!defined($constantName)) {
            throw new \InvalidArgumentException('Unknown action passed: ' . $action);
        }
        return constant($constantName);
    }
}
