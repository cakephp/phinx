<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class RemoveColumn extends Action
{

    protected $column;

    public function __construct(Table $table, Column $column)
    {
        $this->table = $table;
        $this->column = $column;
    }

    public static function build(Table $table, $columnName)
    {
        $column = new Column();
        $column->setName($columnName);
        return new static($table, $column);
    }

    public function getColumn()
    {
        return $this->column;
    }
}
