<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Action;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class ChangeColumn extends Action
{
    /**
     * The column definition
     *
     * @var \Phinx\Db\Table\Column
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
     * @param \Phinx\Db\Table\Table $table The table to alter
     * @param mixed $columnName The name fo the column to change
     * @param \Phinx\Db\Table\Column $column The column definition
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
     * @param \Phinx\Db\Table\Table $table The table to alter
     * @param mixed $columnName The name of the column to change
     * @param mixed $type The type of the column
     * @param mixed $options Additional options for the column
     *
     * @return \Phinx\Db\Action\ChangeColumn
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
     * @return \Phinx\Db\Table\Column
     */
    public function getColumn()
    {
        return $this->column;
    }
}
