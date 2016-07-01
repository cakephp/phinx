<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Db\Adapter\QueryBindInterface;

class PdoAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PdoAdapter
     */
    private $adapter;

    public function setUp()
    {
        $this->adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', array(array('foo' => 'bar')));
        $this->conn = $this->getMockBuilder('PDOMock')
            ->disableOriginalConstructor()
            ->setMethods(array( 'query', 'exec', 'quote' ))
            ->getMock();
    }

    public function tearDown()
    {
        unset($this->adapter);
    }

    public function testOptions()
    {
        $options = $this->adapter->getOptions();
        $this->assertArrayHasKey('foo', $options);
        $this->assertEquals('bar', $options['foo']);
    }

    public function testSchemaTableName()
    {
        $this->adapter->setSchemaTableName('schema_table_test');
        $this->assertEquals('schema_table_test', $this->adapter->getSchemaTableName());
    }

    public function testExecute()
    {
        $rowCount = 123;
        $query = 'SELECT foo FROM bar';
        $mockPdo = $this->getMockPdo();
        $mockPdo->expects($this->once())
            ->method('exec')
            ->with($this->equalTo($query))
            ->will($this->returnValue($rowCount));
        $this->adapter->setConnection($mockPdo);

        $this->assertEquals($rowCount, $this->adapter->execute($query));
    }

    /**
     * @dataProvider queryParams
     */
    public function testExecuteParams(array $params)
    {
        $rowCount = 123;
        $query = 'SELECT foo FROM bar WHERE foo = :val1 OR foo = :val2'; // The query string, and placeholders, do not matter for this test.
        $mockPdo = $this->getMockPdo();
        $mockPdoStmt = $this->getMockPdoStatement();

        $mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($query))
            ->will($this->returnValue($mockPdoStmt));

        $i = 0;
        foreach ($params as $name => $bind) {
            if (is_int($name)) {
                $name = $i + 1;
                        }

            if ($bind instanceof QueryBindInterface) {
                $value = $bind->getValue();
                $type = $bind->getBindType();
            } else {
                $value = $bind;
                $type = \PDO::PARAM_STR;
            }

            $mockPdoStmt->expects($this->at($i))->method('bindValue')->with(
                $this->equalTo($name),
                $this->equalTo($value),
                $this->equalTo($type)
            );
            $i++;
        }

        $mockPdoStmt->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($mockPdoStmt));
        $mockPdoStmt->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue($rowCount));

        $this->adapter->setConnection($mockPdo);

        $this->assertEquals($rowCount, $this->adapter->execute($query, $params));
    }

    public function testQuery()
    {
        $query = 'SELECT foo FROM bar';
        $mockPdo = $this->getMockPdo();
        $mockPdoStmt = $this->getMockPdoStatement();

        $mockPdo->expects($this->once())
            ->method('query')
            ->with($this->equalTo($query))
            ->will($this->returnValue($mockPdoStmt));

        $this->adapter->setConnection($mockPdo);

        $this->assertEquals($mockPdoStmt, $this->adapter->query($query));
    }

    /**
     * @dataProvider queryParams
     */
    public function testQueryParams(array $params)
    {
        $rowCount = 123;
        $query = 'SELECT foo FROM bar WHERE foo = :val1 OR foo = :val2'; // The query string, and placeholders, do not matter for this test.
        $mockPdo = $this->getMockPdo();
        $mockPdoStmt = $this->getMockPdoStatement();

        $mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($query))
            ->will($this->returnValue($mockPdoStmt));

        $i = 0;
        
        foreach ($params as $name => $bind) {
            if (is_int($name)) {
                $name = $i + 1;
            }

            if ($bind instanceof QueryBindInterface) {
                $value = $bind->getValue();
                $type = $bind->getBindType();
            } else {
                $value = $bind;
                $type = \PDO::PARAM_STR;
            }

            $mockPdoStmt->expects($this->at($i))->method('bindValue')->with(
                $this->equalTo($name),
                $this->equalTo($value),
                $this->equalTo($type)
            );

            $i++;
        }

        $mockPdoStmt->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($mockPdoStmt));
        $mockPdoStmt->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue($rowCount));

        $this->adapter->setConnection($mockPdo);

        $this->assertEquals($rowCount, $this->adapter->execute($query, $params));
    }

    /**
     * @dataProvider bindAdapterTypeProvider
     * @param $bindType
     * @param $adapterBindType
     */
    public function testGetAdapterBindParameterType($bindType, $adapterBindType)
    {
        $this->assertEquals($adapterBindType, $this->adapter->getAdapterBindParamType($bindType));
    }

    /**
     * @return array
     */
    public function queryParams()
    {
        /** @var \Phinx\Db\Adapter\QueryBindInterface|\PHPUnit_Framework_MockObject_Builder_InvocationMocker|\PHPUnit_Framework_MockObject_MockObject $mockQueryBind1 */
        $mockQueryBind1 = $this->getMock('Phinx\Db\Adapter\QueryBindInterface');
        $mockQueryBind1->expects($this->any())->method('getValue')->will($this->returnValue(1));
        $mockQueryBind1->expects($this->any())->method('getBindType')->will($this->returnValue(QueryBindInterface::TYPE_INT));

        /** @var \Phinx\Db\Adapter\QueryBindInterface|\PHPUnit_Framework_MockObject_Builder_InvocationMocker|\PHPUnit_Framework_MockObject_MockObject $mockQueryBind2 */
        $mockQueryBind2 = $this->getMock('Phinx\Db\Adapter\QueryBindInterface');
        $mockQueryBind2->expects($this->any())->method('getValue')->will($this->returnValue('fiz'));
        $mockQueryBind2->expects($this->any())->method('getBindType')->will($this->returnValue(QueryBindInterface::TYPE_STR));

        return array(
            array(
                array(
                    1,
                    'fiz'
                )
            ),
            array(
                array(
                    3 => 1,
                    8 => 'fiz'
                )
            ),
            array(
                array(
                    ':val1' => 1,
                    ':val2' => 'fiz'
                )
            ),
            array(
                array(
                    ':val1' => $mockQueryBind1,
                    ':val2' => $mockQueryBind2
                )
            )
        );
    }

    public function bindAdapterTypeProvider()
    {
        return array(
            array(QueryBindInterface::TYPE_BOOL, \PDO::PARAM_BOOL),
            array(QueryBindInterface::TYPE_NULL, \PDO::PARAM_NULL),
            array(QueryBindInterface::TYPE_INT, \PDO::PARAM_INT),
            array(QueryBindInterface::TYPE_STR, \PDO::PARAM_STR),
            array(QueryBindInterface::TYPE_LOB, \PDO::PARAM_LOB)
        );
    }

    /**
     * @return \PDO|\PHPUnit_Framework_MockObject_Builder_InvocationMocker|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockPdo()
    {
        return $this->getMock('\Test\Phinx\Db\Adapter\PDOMock');
    }

    /**
     * @return \PDOStatement|\PHPUnit_Framework_MockObject_Builder_InvocationMocker|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockPdoStatement()
    {
        return $this->getMock('\PDOStatement');
    }
}
