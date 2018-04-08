<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class RenameColumn extends Action
{

    protected $table;

    protected $column;

    protected $newName;

    public function __construct(Table $table, Column $column, $newName)
    {
        $this->table = $table;
        $this->newName = newName;
        $this->column = $column;
    }

    public static function build(Table $table, $columnName, $newName)
    {
        $column = new Column();
        $column->setName($columnName);
        return new static($table, $column, $newName);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumn()
    {
        return $this->column;
    }

    public function getNewName()
    {
        return $this->newName;
    }
}
