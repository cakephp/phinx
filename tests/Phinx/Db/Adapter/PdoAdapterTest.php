<?php

namespace Test\Phinx\Db\Adapter;

use PHPUnit\Framework\TestCase;

class PdoAdapterTestPDOMock extends \PDO
{
    public function __construct()
    {
    }
}

/**
 * A mock PDO that stores its last exec()'d SQL that can be retrieved for queries.
 *
 * This exists as $this->getMockForAbstractClass('\PDO') fails under PHP5.4 and
 * an older PHPUnit; a PDO instance cannot be serialised.
 */
class PdoAdapterTestPDOMockWithExecChecks extends PdoAdapterTestPDOMock
{
    private $sql;

    public function exec($sql)
    {
        $this->sql = $sql;
    }

    public function getExecutedSqlForTest()
    {
        return $this->sql;
    }
}

class PdoAdapterTest extends TestCase
{
    private $adapter;

    public function setUp()
    {
        $this->adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', [['foo' => 'bar']]);
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

    public function testOptionsSetConnection()
    {
        $this->assertNull($this->adapter->getConnection());

        $connection = new PdoAdapterTestPDOMock();
        $this->adapter->setOptions(['connection' => $connection]);

        $this->assertSame($connection, $this->adapter->getConnection());
    }

    public function testOptionsSetSchemaTableName()
    {
        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());
        $this->adapter->setOptions(['default_migration_table' => 'schema_table_test']);
        $this->assertEquals('schema_table_test', $this->adapter->getSchemaTableName());
    }

    public function testSchemaTableName()
    {
        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());
        $this->adapter->setSchemaTableName('schema_table_test');
        $this->assertEquals('schema_table_test', $this->adapter->getSchemaTableName());
    }

    /**
     * @dataProvider getVersionLogDataProvider
     */
    public function testGetVersionLog($versionOrder, $expectedOrderBy)
    {
        $adapter = $this->getMockForAbstractClass(
            '\Phinx\Db\Adapter\PdoAdapter',
            [['version_order' => $versionOrder]],
            '',
            true,
            true,
            true,
            ['fetchAll', 'getSchemaTableName']
        );

        $schemaTableName = 'log';
        $adapter->expects($this->once())
            ->method('getSchemaTableName')
            ->will($this->returnValue($schemaTableName));

        $mockRows = [
            [
                'version' => '20120508120534',
                'key' => 'value'
            ],
            [
                'version' => '20130508120534',
                'key' => 'value'
            ],
        ];

        $adapter->expects($this->once())
            ->method('fetchAll')
            ->with("SELECT * FROM $schemaTableName ORDER BY $expectedOrderBy")
            ->will($this->returnValue($mockRows));

        // we expect the mock rows but indexed by version creation time
        $expected = [
            '20120508120534' => [
                'version' => '20120508120534',
                'key' => 'value'
            ],
            '20130508120534' => [
                'version' => '20130508120534',
                'key' => 'value'
            ],
        ];

        $this->assertEquals($expected, $adapter->getVersionLog());
    }

    public function getVersionLogDataProvider()
    {
        return [
            'With Creation Time Version Order' => [
                \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME, 'version ASC'
            ],
            'With Execution Time Version Order' => [
                \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME, 'start_time ASC, version ASC'
            ],
        ];
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Invalid version_order configuration option
     */
    public function testGetVersionLogInvalidVersionOrderKO()
    {
        $adapter = $this->getMockForAbstractClass(
            '\Phinx\Db\Adapter\PdoAdapter',
            [['version_order' => 'invalid']]
        );

        $adapter->getVersionLog();
    }

    /**
     * Tests that execute() can be called on the adapter, and that the SQL is passed through to the PDO.
     */
    public function testExecuteCanBeCalled()
    {
        $pdo = new PdoAdapterTestPDOMockWithExecChecks();

        $this->adapter->setConnection($pdo);

        $this->adapter->execute('SELECT 1');

        $this->assertSame('SELECT 1;', $pdo->getExecutedSqlForTest());
    }

    public function testExecuteRightTrimsSemiColons()
    {
        $pdo = new PdoAdapterTestPDOMockWithExecChecks();

        $this->adapter->setConnection($pdo);

        $this->adapter->execute('SELECT 1;;');

        $this->assertSame('SELECT 1;', $pdo->getExecutedSqlForTest());
    }
}
