<?php


namespace Phinx\Db;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\View\Condition;
use Phinx\Db\View\Join;

class View {


    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $table_name;

    /**
     * @var array
     */
    protected $joins;


    /**
     * @var Condition|null
     */
    protected $condition = null;

    /**
     * @var View\Column[]
     */
    protected $columns = array();

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @return null|\Phinx\Db\View\Condition
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * Class constructor
     * @param string $name View name
     * @param AdapterInterface $adapter The database adapter to use
     */
    public function __construct($name, AdapterInterface $adapter = null) {
        $this->setName($name);

        if (null !== $adapter) {
            $this->setAdapter($adapter);
        }
        $this->setJoins(array());
    }

    public function create() {
        $this->getAdapter()->createView($this);
    }

    public function drop()
    {
        $this->getAdapter()->dropView($this->getName());
    }

    /**
     * Sets the view name.
     *
     * @param string $name View Name
     * @return View
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * Sets the joins that are used in constructing the view
     * @param array $joins
     * @return View
     */
    public function setJoins($joins)
    {
        $this->joins = $joins;
        return $this;
    }

    /**
     * Gets the joins that are used to construct the view
     * @return Join[]
     */
    public function getJoins()
    {
        return $this->joins;
    }

    /**
     * Sets the table name used as the foundation of this view
     * @param string $table_name The table's name
     * @return View
     */
    public function setTableName($table_name)
    {
        $this->table_name = $table_name;
        return $this;
    }

    /**
     * Gets the name of the table associated with this view
     * @return string
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * Gets the view name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }



    /**
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter Database Adapter
     * @return Table
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    private function _createJoin($type, $table, $criteria, $alias = null) {
        if(!$table instanceof View\Table) {

            $table = new View\Table($table);
            if(!is_null($alias)) {
                $table->setAlias($alias);
            }
        }

        $this->joins[] = new Join($type, $table, $criteria);
        return $this;
    }

    public function addInnerJoin($table, $criteria, $alias = null) {
        return $this->_createJoin('inner', $table, $criteria, $alias);
    }

    public function addJoin($join) {
        $this->joins[] = $join;
        return $this;
    }

    public function setCondition(Condition $condition) {
        $this->condition = $condition;
        return $this;

    }

    public function addColumn($name, $table, $alias = null) {
        if($name instanceof View\Column) {
           $column = $name;
        } else {
            if(!$table instanceof View\Table) {
                $table = new View\Table($table);
            }
            $column = new View\Column($name, $table);
        }
        if(!is_null($alias)) {
            $column->setAlias($alias);
        }

        $this->columns[] = $column;
        return $this;
    }

    /**
     * @return \Phinx\Db\View\Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }




}





