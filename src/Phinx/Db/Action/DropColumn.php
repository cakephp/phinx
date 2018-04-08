<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class DropColumn extends Action
{

    protected $table;

    protected $columnName;

    public function __construct(Table $table, $columnName)
    {
        $this->table = $table;
        $this->columnName = $columnName;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumnName()
    {
        return $this->columnName;
    }
}
