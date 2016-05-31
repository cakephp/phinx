<?php
/**
 * Created by PhpStorm.
 * User: courtney
 * Date: 31/03/15
 * Time: 5:27 PM
 */

namespace Phinx\Db\Adapter;


class QueryBindTest extends \PHPUnit_Framework_TestCase
{
    public function testConstants()
    {
        $this->assertSame(\PDO::PARAM_BOOL, QueryBind::TYPE_BOOL);
        $this->assertSame(\PDO::PARAM_INT, QueryBind::TYPE_INT);
        $this->assertSame(\PDO::PARAM_STR, QueryBind::TYPE_STR);
        $this->assertSame(\PDO::PARAM_LOB, QueryBind::TYPE_LOB);
        $this->assertSame(\PDO::PARAM_NULL, QueryBind::TYPE_NULL);
    }

    public function testGetValue()
    {
        $value = 123;
        $queryBind = new QueryBind($value);

        $this->assertEquals($value, $queryBind->getValue());
    }

    public function testDefaultBindType()
    {
        $queryBind = new QueryBind(123);

        $this->assertSame(QueryBind::TYPE_STR, $queryBind->getBindType());
    }

    public function testSetBindTypeOnConstruct()
    {
        $queryBind = new QueryBind(123, QueryBind::TYPE_INT);

        $this->assertSame(QueryBind::TYPE_INT, $queryBind->getBindType());
    }

    public function testSetBindType()
    {
        $queryBind = new QueryBind(123);
        $queryBind->setBindType(QueryBind::TYPE_INT);

        $this->assertSame(QueryBind::TYPE_INT, $queryBind->getBindType());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidValueException()
    {
        $queryBind = new QueryBind(array('foo' => 'bar'));
    }
}
