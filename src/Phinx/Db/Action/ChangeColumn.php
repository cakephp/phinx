<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;

class ChangeColumn extends Action
{

    protected $table;

    protected $column;

    protected $columnName;

    public function __construct(Table $table, $columnName, Column $column)
    {
        $this->table = $table;
        $this->columnName = $columnName;
        $this->column = $column;

        // if the name was omitted use the existing column name
        if ($column->getName() === null || strlen($column->getName()) === 0) {
            $column->setName($columnName);
        }
    }

    public static function build(Table $table, $columnName, $type = null, $options = [])
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options); // map options to column methods

        return new static($table, $columnName, $column);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumnName()
    {
        return $this->columnName;
    }

    public function getColumn()
    {
        return $this->column;
    }
}
