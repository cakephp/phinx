<?php

namespace Phinx\Db\Action;

abstract class Action
{

    /**
     * @var Phinx\Db\Table\Table
     */
    protected $table;

    /**
     * The table this action will be applied to
     *
     * @return Phinx\Db\Table\Table
     */
    public function getTable()
    {
        return $this->table;
    }
}
