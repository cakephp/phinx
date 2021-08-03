<?php

namespace Test\Phinx\Db\Adapter;

use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Test\Phinx\Db\Mock\PdoAdapterTestPDOMock;
use Test\Phinx\Db\Mock\PdoAdapterTestPDOMockWithExecChecks;

class PdoAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\PdoAdapter|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adapter;

    protected function setUp(): void
    {
        $this->adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', [['foo' => 'bar']]);
    }

    protected function tearDown(): void
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
        $connection = new PdoAdapterTestPDOMock();
        $this->adapter->setOptions(['connection' => $connection]);

        $this->assertSame($connection, $this->adapter->getConnection());
    }

    public function testOptionsSetSchemaTableName()
    {
        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());
        $this->adapter->setOptions(['migration_table' => 'schema_table_test']);
        $this->assertEquals('schema_table_test', $this->adapter->getSchemaTableName());
    }

    public function testOptionsSetDefaultMigrationTableThrowsDeprecation()
    {
        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());

        $this->expectDeprecation();
        $this->expectExceptionMessage('The default_migration_table setting for adapter has been deprecated since 0.13.0. Use `migration_table` instead.');
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
            ['fetchAll', 'getSchemaTableName', 'quoteTableName']
        );

        $schemaTableName = 'log';
        $adapter->expects($this->once())
            ->method('getSchemaTableName')
            ->will($this->returnValue($schemaTableName));
        $adapter->expects($this->once())
            ->method('quoteTableName')
            ->with($schemaTableName)
            ->will($this->returnValue("'$schemaTableName'"));

        $mockRows = [
            [
                'version' => '20120508120534',
                'key' => 'value',
            ],
            [
                'version' => '20130508120534',
                'key' => 'value',
            ],
        ];

        $adapter->expects($this->once())
            ->method('fetchAll')
            ->with("SELECT * FROM '$schemaTableName' ORDER BY $expectedOrderBy")
            ->will($this->returnValue($mockRows));

        // we expect the mock rows but indexed by version creation time
        $expected = [
            '20120508120534' => [
                'version' => '20120508120534',
                'key' => 'value',
            ],
            '20130508120534' => [
                'version' => '20130508120534',
                'key' => 'value',
            ],
        ];

        $this->assertEquals($expected, $adapter->getVersionLog());
    }

    public function getVersionLogDataProvider()
    {
        return [
            'With Creation Time Version Order' => [
                \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME, 'version ASC',
            ],
            'With Execution Time Version Order' => [
                \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME, 'start_time ASC, version ASC',
            ],
        ];
    }

    public function testGetVersionLogInvalidVersionOrderKO()
    {
        $this->expectExceptionMessage('Invalid version_order configuration option');
        $adapter = $this->getMockForAbstractClass(
            '\Phinx\Db\Adapter\PdoAdapter',
            [['version_order' => 'invalid']]
        );

        $this->expectException(RuntimeException::class);

        $adapter->getVersionLog();
    }

    public function testGetVersionLongDryRun()
    {
        $adapter = $this->getMockForAbstractClass(
            '\Phinx\Db\Adapter\PdoAdapter',
            [['version_order' => \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME]],
            '',
            true,
            true,
            true,
            ['isDryRunEnabled', 'fetchAll', 'getSchemaTableName', 'quoteTableName']
        );

        $schemaTableName = 'log';

        $adapter->expects($this->once())
            ->method('isDryRunEnabled')
            ->will($this->returnValue(true));
        $adapter->expects($this->once())
            ->method('getSchemaTableName')
            ->will($this->returnValue($schemaTableName));
        $adapter->expects($this->once())
            ->method('quoteTableName')
            ->with($schemaTableName)
            ->will($this->returnValue("'$schemaTableName'"));
        $adapter->expects($this->once())
            ->method('fetchAll')
            ->with("SELECT * FROM '$schemaTableName' ORDER BY version ASC")
            ->will($this->throwException(new PDOException()));

        $this->assertEquals([], $adapter->getVersionLog());
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
