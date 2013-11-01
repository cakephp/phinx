<?php

namespace Phinx\Db\View;


class Table {

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $alias;

    function __construct($name)
    {
        $this->name = $name;
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




} 