<?php

namespace Phinx\Db\Plan;

use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;

class NewTable
{
    protected $table;

    protected $columns = [];

    protected $indexes = [];

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function addColumn(Column $column)
    {
        $this->columns[] = $column;
    }

    public function addIndex(Index $index)
    {
        $this->indexes[] = $index;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getIndexes()
    {
        return $this->indexes();
    }
}
