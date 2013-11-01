<?php

namespace Phinx\Db\View;


class Column {

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $alias;

    function __construct($name, $table)
    {
        $this->name = $name;
        $this->table = $table;
    }

    /**
     * @param null|string $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * @return null|string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param \Phinx\Db\View\Table $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * @return \Phinx\Db\View\Table
     */
    public function getTable()
    {
        return $this->table;
    }




} 