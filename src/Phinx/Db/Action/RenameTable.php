<?php

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class RenameTable extends Action
{

    protected $newName;

    public function __construct(Table $table, $newName)
    {
        $this->newName = $newName;
        $this->table = $table;
    }

    public function getNewName()
    {
        return $this->newName;
    }
}
