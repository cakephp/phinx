<?php

namespace Phinx\Db\Plan;

use Phinx\Db\Action\Action;

class Intent
{

    protected $actions = [];

    public function addAction(Action $action)
    {
        $this->actions[] = $action;
    }

    public function getActions()
    {
        return $this->actions;
    }

    public function merge(Intent $another)
    {
        $this->actions = array_merge($this->actions, $another->getActions());
    }
}
