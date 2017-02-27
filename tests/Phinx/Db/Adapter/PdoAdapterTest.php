<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\PdoAdapter;

class PdoAdapterTest extends \PHPUnit_Framework_TestCase
{
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
    
    /**
     * @dataProvider getVersionLogDataProvider
     */
    public function testGetVersionLog($versionOrder, $expectedOrderBy)
    {
        $adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', 
            array(array('version_order' => $versionOrder)), '', true, true, true,
            array('fetchAll', 'getSchemaTableName'));

        $schemaTableName = 'log';
        $adapter->expects($this->once())
            ->method('getSchemaTableName')
            ->will($this->returnValue($schemaTableName));

        $mockRows = array (
            array(
                'version' => '20120508120534',
                'key' => 'value'
            ),
            array(
                'version' => '20130508120534',
                'key' => 'value'
            ),
        );

        $adapter->expects($this->once())
            ->method('fetchAll')
            ->with("SELECT * FROM $schemaTableName ORDER BY $expectedOrderBy")
            ->will($this->returnValue($mockRows));

        // we expect the mock rows but indexed by version creation time
        $expected = array(
            '20120508120534' => array(
                'version' => '20120508120534',
                'key' => 'value'
            ),
            '20130508120534' => array(
                'version' => '20130508120534',
                'key' => 'value'
            ),
        );

        $this->assertEquals($expected, $adapter->getVersionLog());
    }

    public function getVersionLogDataProvider()
    {
        return array(
            'With Creation Time Version Order' => array(
                \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME, 'version ASC'
            ),
            'With Execution Time Version Order' => array(
                \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME, 'start_time ASC, version ASC'
            ),
        );
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Invalid version_order configuration option
     */
    public function testGetVersionLogInvalidVersionOrderKO()
    {
        $adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', 
            array(array('version_order' => 'invalid')));

        $adapter->getVersionLog();
    }
}
