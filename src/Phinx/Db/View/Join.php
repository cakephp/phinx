<?php

namespace Phinx\Db\View;


class Join {

    /**
     * @var string
     */
    protected $type;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var array
     */
    protected $criteria;

    function __construct($type, Table $table, $criteria = null)
    {
        $this->criteria = $criteria;
        $this->table = $table;
        $this->type = $type;
    }

    /**
     * @param Condition $criteria
     */
    public function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * @return Condition
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * @param Table $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


}