<?php

namespace Phinx\Db\Plan;

use Phinx\Db\Action\Action;
use Phinx\Db\Table\Table;

class AlterTable
{
    protected $table;

    protected $actions = [];

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function addAction(Action $action)
    {
        $this->actions[] = $action;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getActions()
    {
        return $this->actions;
    }
}
