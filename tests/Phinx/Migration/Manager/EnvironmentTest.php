<?php

namespace Test\Phinx\Migration\Manager;

use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Migration\Manager\Environment;
use Phinx\Migration\MigrationInterface;
use PHPUnit\Framework\TestCase;

class PDOMock extends \PDO
{
    public $attributes = [];

    public function __construct()
    {
    }

    public function getAttribute($attribute)
    {
        return isset($this->attributes[$attribute]) ? $this->attributes[$attribute] : 'pdomock';
    }

    public function setAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
    }
}

class EnvironmentTest extends TestCase
{
    /**
     * @var \Phinx\Migration\Manager\Environment
     */
    private $environment;

    public function setUp()
    {
        $this->environment = new Environment('test', []);
    }

    public function testConstructorWorksAsExpected()
    {
        $env = new Environment('testenv', ['foo' => 'bar']);
        $this->assertEquals('testenv', $env->getName());
        $this->assertArrayHasKey('foo', $env->getOptions());
    }

    public function testSettingTheName()
    {
        $this->environment->setName('prod123');
        $this->assertEquals('prod123', $this->environment->getName());
    }

    public function testSettingOptions()
    {
        $this->environment->setOptions(['foo' => 'bar']);
        $this->assertArrayHasKey('foo', $this->environment->getOptions());
    }

    public function testConnectionOptionsCanBeSpecifiedWithDsn()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database';
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $options = $env->getOptions();
        $this->assertArrayHasKey('adapter', $options);
        $this->assertSame('pdomock', $options['adapter']);
        $this->assertArrayHasKey('user', $options);
        $this->assertSame('phinx', $options['user']);
        $this->assertArrayHasKey('pass', $options);
        $this->assertSame('supersecret', $options['pass']);
        $this->assertArrayHasKey('host', $options);
        $this->assertSame('my-database-host', $options['host']);
        $this->assertArrayHasKey('port', $options);
        $this->assertEquals(1234, $options['port']);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('my_app_database', $options['name']);
    }

    public function testDsnOnlySetsSpecifiedOptions()
    {
        $dsn = 'pdomock://my-database-host/my_app_database';
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $options = $env->getOptions();
        $this->assertArrayNotHasKey('user', $options);
        $this->assertArrayNotHasKey('pass', $options);
        $this->assertArrayNotHasKey('port', $options);
    }

    public function testDsnGetsRemovedWhenAfterSuccessfulParsing()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database';
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $this->assertArrayNotHasKey('dsn', $env->getOptions());
    }

    public function testOptionsAreLeftAsIsOnInvalidDsn()
    {
        $dsn = 'pdomock://phinx:supersecret@localhost:12badport34/db_name';
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $options = $env->getOptions();
        $this->assertArrayHasKey('dsn', $options);
        $this->assertSame($dsn, $options['dsn']);
    }

    public function testDsnDoesNotOverrideSpecifiedOptions()
    {
        $dsn = 'pdomock://my-database-host:1234/my_web_database';
        $env = new Environment('testenv', [
            'user' => 'api_user',
            'dsn' => $dsn,
            'name' => 'my_api_database',
        ]);
        $options = $env->getOptions();
        $this->assertArrayHasKey('user', $options);
        $this->assertSame('api_user', $options['user']);
        $this->assertArrayNotHasKey('pass', $options);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('my_api_database', $options['name']);
    }

    public function testNoModificationToOptionsOnInvalidDsn()
    {
        $dsn = 'pdomock://phinx:supersecret@localhost:12badport34/db_name';
        $env = new Environment('testenv', [
            'user' => 'api_user',
            'dsn' => $dsn,
            'name' => 'my_api_database',
        ]);
        $options = $env->getOptions();
        $this->assertArrayHasKey('user', $options);
        $this->assertSame('api_user', $options['user']);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('my_api_database', $options['name']);
        $this->assertArrayHasKey('dsn', $options);
    }

    public function testDsnQueryProvidesAdditionalOptions()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database?charset=utf8&unrelated=thing&';
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $options = $env->getOptions();
        $this->assertArrayHasKey('charset', $options);
        $this->assertSame('utf8', $options['charset']);
        $this->assertArrayHasKey('unrelated', $options);
        $this->assertSame('thing', $options['unrelated']);
        $this->assertArrayNotHasKey('query', $options);
    }

    public function testDsnQueryDoesNotOverrideDsnParameters()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database?port=80&host=another-host';
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $options = $env->getOptions();
        $this->assertSame('my-database-host', $options['host']);
        $this->assertEquals(1234, $options['port']);
    }

    public function dataProviderValidDsn()
    {
        return [
            ['mysql://user:pass@host:1234/name?charset=utf8'],
            ['postgres://user:pass@host/name?'],
            ['mssql://user:@host:1234/name'],
            ['sqlite3://user@host:1234/name'],
            ['pdomock://host:1234/name'],
            ['pdomock://user:pass@host/name'],
            ['pdomock://host/name'],
            // The RegEx that parses the DSN is purely to extract information
            // from the string in the right order, it is not responsible for
            // ensuring that the information is correct. Technically the
            // following, whilst useless, is valid (validation will happen
            // during connection):
            ['£$%^&}{@>://$%*(*&^}{:?£}{@^>}"{$:1234/>@"}%{^>£}:/^'],
            ['pdomock://user:pass@host/:1234/name'],
            ['pdomock://user:pa:ss@host:1234/name'],
        ];
    }

    public function dataProviderInvalidDsn()
    {
        return [
            ['pdomock://user:pass@host:/name'],
            ['pdomock://user:pass@host:1234/ '],
            ['pdomock://user:pass@:1234/name'],
            ['pdomock://:pass@host:1234/name'],
            ['://user:pass@host:1234/name'],
            ['pdomock:/user:p@ss@host:1234/name'],
            ['pdomock://user:pass@host:/1234name'],
            ['pdomock://user:pass@host:01234/name'],
        ];
    }

    /** @dataProvider \Test\Phinx\Migration\Manager\EnvironmentTest::dataProviderValidDsn() */
    public function testValidDsn($dsn)
    {
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $this->assertArrayNotHasKey('dsn', $env->getOptions());
    }

    /** @dataProvider \Test\Phinx\Migration\Manager\EnvironmentTest::dataProviderInvalidDsn() */
    public function testInvalidDsn($dsn)
    {
        $env = new Environment('testenv', ['dsn' => $dsn]);
        $this->assertArrayHasKey('dsn', $env->getOptions());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Adapter "fakeadapter" has not been registered
     */
    public function testInvalidAdapter()
    {
        $this->environment->setOptions(['adapter' => 'fakeadapter']);
        $this->environment->getAdapter();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNoAdapter()
    {
        $this->environment->getAdapter();
    }

    public function testGetAdapterWithExistingPdoInstance()
    {
        $adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', [['foo' => 'bar']]);
        AdapterFactory::instance()->registerAdapter('pdomock', $adapter);
        $this->environment->setOptions(['connection' => new PDOMock()]);
        $options = $this->environment->getAdapter()->getOptions();
        $this->assertEquals('pdomock', $options['adapter']);
    }

    public function testSetPdoAttributeToErrmodeException()
    {
        $pdoMock = new PDOMock();
        $adapter = $this->getMockForAbstractClass('\Phinx\Db\Adapter\PdoAdapter', [['foo' => 'bar']]);
        AdapterFactory::instance()->registerAdapter('pdomock', $adapter);
        $this->environment->setOptions(['connection' => $pdoMock]);
        $options = $this->environment->getAdapter()->getOptions();
        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $options['connection']->getAttribute(\PDO::ATTR_ERRMODE));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The specified connection is not a PDO instance
     */
    public function testGetAdapterWithBadExistingPdoInstance()
    {
        $this->environment->setOptions(['connection' => new \stdClass()]);
        $this->environment->getAdapter();
    }

    public function testTablePrefixAdapter()
    {
        $this->environment->setOptions(['table_prefix' => 'tbl_', 'adapter' => 'mysql']);
        $this->assertInstanceOf('Phinx\Db\Adapter\TablePrefixAdapter', $this->environment->getAdapter());

        $tablePrefixAdapter = $this->environment->getAdapter();
        $this->assertInstanceOf('Phinx\Db\Adapter\MysqlAdapter', $tablePrefixAdapter->getAdapter()->getAdapter());
    }

    public function testSchemaName()
    {
        $this->assertEquals('phinxlog', $this->environment->getSchemaTableName());

        $this->environment->setSchemaTableName('changelog');
        $this->assertEquals('changelog', $this->environment->getSchemaTableName());
    }

    public function testCurrentVersion()
    {
        $stub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $stub->expects($this->any())
             ->method('getVersions')
             ->will($this->returnValue(['20110301080000']));

        $this->environment->setAdapter($stub);

        $this->assertEquals('20110301080000', $this->environment->getCurrentVersion());
    }

    public function testExecutingAMigrationUp()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->setMethods(['up'])
            ->getMock();
        $upMigration->expects($this->once())
                    ->method('up');

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
    }

    public function testExecutingAMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // down
        $downMigration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->setMethods(['down'])
            ->getMock();
        $downMigration->expects($this->once())
                      ->method('down');

        $this->environment->executeMigration($downMigration, MigrationInterface::DOWN);
    }

    public function testExecutingAMigrationWithTransactions()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('beginTransaction');

        $adapterStub->expects($this->once())
                    ->method('commitTransaction');

        $adapterStub->expects($this->exactly(2))
                    ->method('hasTransactions')
                    ->will($this->returnValue(true));

        $this->environment->setAdapter($adapterStub);

        // migrate
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20110301080000'])
            ->setMethods(['up'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('up');

        $this->environment->executeMigration($migration, MigrationInterface::UP);
    }

    public function testExecutingAChangeMigrationUp()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->setMethods(['change'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::UP);
    }

    public function testExecutingAChangeMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMockBuilder('\Phinx\Migration\AbstractMigration')
            ->setConstructorArgs(['mockenv', '20130301080000'])
            ->setMethods(['change'])
            ->getMock();
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
    }

    public function testGettingInputObject()
    {
        $mock = $this->getMockBuilder('\Symfony\Component\Console\Input\InputInterface')
            ->getMock();
        $this->environment->setInput($mock);
        $inputObject = $this->environment->getInput();
        $this->assertInstanceOf('\Symfony\Component\Console\Input\InputInterface', $inputObject);
    }
}
