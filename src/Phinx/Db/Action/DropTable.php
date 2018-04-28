<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class DropTable extends Action
{

    protected $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }
}
