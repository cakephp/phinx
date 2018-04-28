<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class CreateTable extends Action
{

    public function __construct(Table $table)
    {
        $this->table = $table;
    }
}
