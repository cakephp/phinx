<?php
/**
 * Created by PhpStorm.
 * User: Daniel
 * Date: 10/29/13
 * Time: 12:53 PM
 */

namespace Test\Phinx\Db;


use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\View\Condition;

class ConditionTest extends \PHPUnit_Framework_TestCase {

    var $adapter;

    public function setUp() {
        //use a mysql adapter, because we have to use something
        $this->adapter = new MysqlAdapter(array());
    }

    function testColumn() {
        $condition = new Condition();
        $condition->column("colA");
        $this->assertEquals("`colA`", $condition->getConditionSQL($this->adapter));
    }

    function testColumnWithTable() {
        $condition = new Condition();
        $condition->column('colA', 'table1');
        $this->assertEquals('`table1`.`colA`', $condition->getConditionSQL($this->adapter));
    }

    function testIntegerLiteral() {
        $condition = new Condition();
        $condition->literal(5);
        $this->assertEquals("5", $condition->getConditionSQL($this->adapter));
    }

    function testStringLiteral() {
        $condition = new Condition();
        $condition->literal("hello");
        $this->assertEquals("'hello'", $condition->getConditionSQL($this->adapter));
    }

    function testArrayStringLiteral() {
        $condition = new Condition();
        $condition->literal(array("hello", "World"));
        $this->assertEquals("('hello','World')", $condition->getConditionSQL($this->adapter));
    }

    function testAnd() {
        $condition = new Condition();
        $condition->aand()
            ->column("colA")
            ->column('colB');
        $this->assertEquals("`colA` AND `colB`", $condition->getConditionSQL($this->adapter));
    }

    function testAndWithOr() {
        $condition = new Condition();
        $condition
            ->aand()
                ->oor()
                    ->column("colA")
                    ->column('colB')
                ->oor()
                    ->column("colC")
                    ->column('colD');
        $this->assertEquals("(`colA` OR `colB`) AND (`colC` OR `colD`)", $condition->getConditionSQL($this->adapter));
    }

    function testComparison() {
        $condition = new Condition();
        $condition->comparison('=')
            ->column("colA")
            ->column('colB');
        $this->assertEquals("`colA` = `colB`", $condition->getConditionSQL($this->adapter));
    }

    function testOrWithComparison() {
        $condition = new Condition();
        $condition
            ->oor()
                ->comparison("<")
                    ->column("colA")
                    ->column('colB')
                ->comparison("=")
                    ->column("colC")
                    ->column('colD');
        $this->assertEquals("`colA` < `colB` OR `colC` = `colD`", $condition->getConditionSQL($this->adapter));
    }

    function testIs() {
        $condition = new Condition();
        $condition
            ->is(true)
                ->is(null)
                    ->column('colA');

        $this->assertEquals("`colA` IS NULL IS TRUE", $condition->getConditionSQL($this->adapter));
    }

    function testIsFalse() {
        $condition = new Condition();
        $condition
            ->is(false)
                ->column('colA');

        $this->assertEquals("`colA` IS FALSE", $condition->getConditionSQL($this->adapter));
    }

    function testInvalidIs() {
        $condition = new Condition();
        try{
            $condition->is("invalid");
            $this->fail("Should have received an invalid argument exception, instead passed");
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e));
        }
    }

    function testIsNot() {
        $condition = new Condition();
        $condition
            ->isNot(false)
                ->isNot("unknown")
                    ->column('colA');

        $this->assertEquals("`colA` IS NOT UNKNOWN IS NOT FALSE", $condition->getConditionSQL($this->adapter));
    }

    function testIn() {
        $condition = new Condition();
        $condition
            ->in()
                ->column('colA')
                ->literal(array(5, 6, 7, 8, 9));
        $this->assertEquals("`colA` IN (5,6,7,8,9)", $condition->getConditionSQL($this->adapter));
    }


}
 