<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Adapter;

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use PDO;
use PDOException;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Util\Literal;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Test\Phinx\DeprecationException;
use Test\Phinx\TestUtils;

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
        $connection = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
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
        TestUtils::throwUserDeprecatedError();

        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());

        $this->expectException(DeprecationException::class);
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
            ['fetchAll', 'getSchemaTableName', 'quoteTableName'],
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
                Config::VERSION_ORDER_CREATION_TIME, 'version ASC',
            ],
            'With Execution Time Version Order' => [
                Config::VERSION_ORDER_EXECUTION_TIME, 'start_time ASC, version ASC',
            ],
        ];
    }

    public function testGetVersionLogInvalidVersionOrderKO()
    {
        $this->expectExceptionMessage('Invalid version_order configuration option');
        $adapter = $this->getMockForAbstractClass(
            '\Phinx\Db\Adapter\PdoAdapter',
            [['version_order' => 'invalid']],
        );

        $this->expectException(RuntimeException::class);

        $adapter->getVersionLog();
    }

    public function testGetVersionLongDryRun()
    {
        $adapter = $this->getMockForAbstractClass(
            '\Phinx\Db\Adapter\PdoAdapter',
            [['version_order' => Config::VERSION_ORDER_CREATION_TIME]],
            '',
            true,
            true,
            true,
            ['isDryRunEnabled', 'fetchAll', 'getSchemaTableName', 'quoteTableName'],
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
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['exec'])->getMock();
        $pdo->expects($this->once())->method('exec')->with('SELECT 1')->will($this->returnValue(1));

        $this->adapter->setConnection($pdo);
        $this->adapter->execute('SELECT 1');
    }

    public function testExecuteRightTrimsSemiColons()
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['exec'])->getMock();
        $pdo->expects($this->once())->method('exec')->with('SELECT 1')->will($this->returnValue(1));

        $this->adapter->setConnection($pdo);
        $this->adapter->execute('SELECT 1;;');
    }

    public function quoteValueDataProvider(): array
    {
        return [
            [1.0, 1.0],
            [2, 2],
            [true, 1],
            [false, 0],
            [null, 'null'],
            [Literal::from('CURRENT_TIMESTAMP'), 'CURRENT_TIMESTAMP'],
        ];
    }

    /**
     * @dataProvider quoteValueDataProvider
     */
    public function testQuoteValue($input, $expected): void
    {
        $method = new ReflectionMethod($this->adapter, 'quoteValue');
        $this->assertSame($expected, $method->invoke($this->adapter, $input));
    }

    public function quoteValueStringDataProvider(): array
    {
        return [
            ['mockvalue', "'mockvalue'"],
            [new Date('2023-01-01'), "'2023-01-01'"],
            [new DateTime('2023-01-01 12:00:00'), "'2023-01-01 12:00:00'"],
        ];
    }

    /**
     * @dataProvider quoteValueStringDataProvider
     */

    public function testQuoteValueString($input, $expected): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['quote'])
            ->getMock();

        $pdo->expects($this->once())
            ->method('quote')
            ->willReturnCallback(function (string $input) {
                return "'$input'";
            });

        $this->adapter->setConnection($pdo);

        $method = new ReflectionMethod($this->adapter, 'quoteValue');
        $this->assertSame($expected, $method->invoke($this->adapter, $input));
    }

    public function defaultValueDefinitionDataProvider(): array
    {
        return [
            ['some string', AdapterInterface::PHINX_TYPE_STRING, " DEFAULT 'some string'"],
            [123, AdapterInterface::PHINX_TYPE_INTEGER, ' DEFAULT 123'],
            [true, AdapterInterface::PHINX_TYPE_BOOLEAN, ' DEFAULT 1'],
            [false, AdapterInterface::PHINX_TYPE_BOOLEAN, ' DEFAULT 0'],
            [null, AdapterInterface::PHINX_TYPE_STRING, ''],
            [Literal::from('foo'), AdapterInterface::PHINX_TYPE_STRING, ' DEFAULT foo'],
            ['CURRENT_TIMESTAMP', AdapterInterface::PHINX_TYPE_STRING, " DEFAULT 'CURRENT_TIMESTAMP'"],
            ['CURRENT_TIMESTAMP', AdapterInterface::PHINX_TYPE_DATETIME, ' DEFAULT CURRENT_TIMESTAMP'],
            ['CURRENT_TIMESTAMP(3)', AdapterInterface::PHINX_TYPE_DATETIME, ' DEFAULT CURRENT_TIMESTAMP(3)'],
            ['CURRENT_TIMESTAMP()', AdapterInterface::PHINX_TYPE_DATETIME, ' DEFAULT CURRENT_TIMESTAMP()'],
            ['CURRENT_TIMESTAMP', AdapterInterface::PHINX_TYPE_TIMESTAMP, ' DEFAULT CURRENT_TIMESTAMP'],
            ['CURRENT_TIME', AdapterInterface::PHINX_TYPE_TIME, ' DEFAULT CURRENT_TIME'],
            ['CURRENT_DATE', AdapterInterface::PHINX_TYPE_DATE, ' DEFAULT CURRENT_DATE'],
            ['NOW', AdapterInterface::PHINX_TYPE_DATETIME, ' DEFAULT NOW'],
        ];
    }

    /**
     * @dataProvider defaultValueDefinitionDataProvider
     */
    public function testGetDefaultValueDefinition($input, $columnType, $expected): void
    {
        /** @var \PDO&\PHPUnit\Framework\MockObject\MockObject $pdo */
        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['quote'])
            ->getMock();

        $pdo->method('quote')
            ->willReturnCallback(function (string $input) {
                return "'$input'";
            });

        $this->adapter->setConnection($pdo);

        $method = new ReflectionMethod($this->adapter, 'getDefaultValueDefinition');
        $this->assertSame($expected, $method->invoke($this->adapter, $input, $columnType));
    }
}
