<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Action;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class AddColumn extends Action
{
    /**
     * The column to add
     *
     * @var \Phinx\Db\Table\Column
     */
    protected $column;

    /**
     * Constructor
     *
     * @param \Phinx\Db\Table\Table $table The table to add the column to
     * @param \Phinx\Db\Table\Column $column The column to add
     */
    public function __construct(Table $table, Column $column)
    {
        parent::__construct($table);
        $this->column = $column;
    }

    /**
     * Returns a new AddColumn object after assembling the given commands
     *
     * @param \Phinx\Db\Table\Table $table The table to add the column to
     * @param mixed $columnName The column name
     * @param mixed $type The column type
     * @param mixed $options The column options
     *
     * @return \Phinx\Db\Action\AddColumn
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
     * @return \Phinx\Db\Table\Column
     */
    public function getColumn()
    {
        return $this->column;
    }
}
