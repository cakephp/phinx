<?php

namespace Test\Phinx\Db;

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\View\Condition;

class ViewTest extends \PHPUnit_Framework_TestCase {

    function testBasicCondition() {
        $adapter =  new MysqlAdapter(array());

        $view = new \Phinx\Db\View("myName", $adapter);

        $condition = new Condition();

        $view->
            setCondition( $condition
                ->aand()
                    ->comparison("=")
                        ->column("col1", "table1")
                        ->column("col2", "table2")
                    ->comparison("<")
                        ->column("col3", "table1")
                        ->column("col4"));

        $this->assertEquals("`table1`.`col1` = `table2`.`col2` AND `table1`.`col3` < `col4`",
            $view->getCondition()->getConditionSQL($adapter));
    }

}