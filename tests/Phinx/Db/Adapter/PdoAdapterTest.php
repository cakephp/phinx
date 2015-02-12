<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\PdoAdapter;

class PdoAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PdoAdapter
     */
    private $adapter;

    public function setUp()
    {
        $this->adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', array(array('foo' => 'bar')));
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

            if (is_array($bind)) {
                $value = $bind['value'];
                $type = isset($bind['type']) ? $bind['type'] : \PDO::PARAM_STR;
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

            if (is_array($bind)) {
                $value = $bind['value'];
                $type = isset($bind['type']) ? $bind['type'] : \PDO::PARAM_STR;
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
     * @return array
     */
    public function queryParams()
    {
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
                    ':val1' => array(
                        'value' => 1,
                        'type' => \PDO::PARAM_INT
                    ),
                    ':val2' => array(
                        'value' => 'fiz',
                    )
                )
            )
        );
    }

    /**
     * @return \PDO|\PHPUnit_Framework_MockObject_Builder_InvocationMocker|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockPdo()
    {
        return $this->getMock('PdoMockable');
    }

    /**
     * @return \PDOStatement|\PHPUnit_Framework_MockObject_Builder_InvocationMocker|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockPdoStatement()
    {
        return $this->getMock('\PDOStatement');
    }
}
