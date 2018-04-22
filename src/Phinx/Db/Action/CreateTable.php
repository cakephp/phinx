<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class CreateTable extends Action
{

    protected $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function getTable()
    {
        return $this->table;
    }
}
