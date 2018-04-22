<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class AddColumn extends Action
{

    protected $table;

    protected $column;

    public function __construct(Table $table, Column $column)
    {
        $this->table = $table;
        $this->column = $column;
    }

    public static function build(Table $table, $columnName, $type = null, $options = [])
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options); // map options to column methods

        return new static($table, $column);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumn()
    {
        return $this->column;
    }
}
