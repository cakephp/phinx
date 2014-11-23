<?php

namespace Test\Phinx\Migration\Manager;

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\Manager\Environment;

class EnvironmentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Migration\Manager\Environment
     */
    private $environment;

    public function setUp()
    {
        $this->environment = new Environment('test', array());
    }

    public function testConstructorWorksAsExpected()
    {
        $env = new Environment('testenv', array('foo' => 'bar'));
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
        $this->environment->setOptions(array('foo' => 'bar'));
        $this->assertArrayHasKey('foo', $this->environment->getOptions());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid adapter specified: fakeadapter
     */
    public function testInvalidAdapter()
    {
        $this->environment->setOptions(array('adapter' => 'fakeadapter'));
        $this->environment->getAdapter();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNoAdapter()
    {
        $this->environment->getAdapter();
    }
    
    public function testTablePrefixAdapter()
    {
        $this->environment->setOptions(array('table_prefix' => 'tbl_', 'adapter' => 'mysql'));
        $this->assertTrue($this->environment->getAdapter() instanceof \Phinx\Db\Adapter\TablePrefixAdapter);
        
        $tablePrefixAdapter = $this->environment->getAdapter();
        $this->assertTrue($tablePrefixAdapter->getAdapter() instanceof \Phinx\Db\Adapter\MysqlAdapter);
    }

    public function testSchemaName()
    {
        $this->assertEquals('phinxlog', $this->environment->getSchemaTableName());

        $this->environment->setSchemaTableName('changelog');
        $this->assertEquals('changelog', $this->environment->getSchemaTableName());
    }

    public function testAdapterFactoryCreatesMysqlAdapter()
    {
        $this->environment->setOptions(array('adapter' => 'mysql'));
        $this->assertTrue($this->environment->getAdapter() instanceof \Phinx\Db\Adapter\MysqlAdapter);
    }

    public function testAdapterFactoryCreatesSqliteAdapter()
    {
        $this->environment->setOptions(array('adapter' => 'sqlite'));
        $this->assertTrue($this->environment->getAdapter() instanceof \Phinx\Db\Adapter\SQLiteAdapter);
    }

    public function testCurrentVersion()
    {
        $stub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $stub->expects($this->any())
             ->method('getVersions')
             ->will($this->returnValue(array('20110301080000')));

        $this->environment->setAdapter($stub);

        $this->assertEquals('20110301080000', $this->environment->getCurrentVersion());
    }

    public function testExecutingAMigrationUp()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // up
        $upMigration = $this->getMock('\Phinx\Migration\AbstractMigration', array('up'), array('20110301080000'));
        $upMigration->expects($this->once())
                    ->method('up');

        $this->environment->executeMigration($upMigration, \Phinx\Migration\MigrationInterface::UP);
    }

    public function testExecutingAMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // down
        $downMigration = $this->getMock('\Phinx\Migration\AbstractMigration', array('down'), array('20110301080000'));
        $downMigration->expects($this->once())
                      ->method('down');

        $this->environment->executeMigration($downMigration, \Phinx\Migration\MigrationInterface::DOWN);
    }

    public function testExecutingAMigrationWithTransactions()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('beginTransaction');

        $adapterStub->expects($this->once())
                    ->method('commitTransaction');

        $adapterStub->expects($this->exactly(2))
                    ->method('hasTransactions')
                    ->will($this->returnValue(true));

        $this->environment->setAdapter($adapterStub);

        // migrate
        $migration = $this->getMock('\Phinx\Migration\AbstractMigration', array('up'), array('20110301080000'));
        $migration->expects($this->once())
                  ->method('up');

        $this->environment->executeMigration($migration, \Phinx\Migration\MigrationInterface::UP);
    }

    public function testExecutingAChangeMigrationUp()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMock('\Phinx\Migration\AbstractMigration', array('change'), array('20130301080000'));
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, \Phinx\Migration\MigrationInterface::UP);
    }

    public function testExecutingAChangeMigrationDown()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('migrated')
                    ->will($this->returnArgument(0));

        $this->environment->setAdapter($adapterStub);

        // migration
        $migration = $this->getMock('\Phinx\Migration\AbstractMigration', array('change'), array('20130301080000'));
        $migration->expects($this->once())
                  ->method('change');

        $this->environment->executeMigration($migration, \Phinx\Migration\MigrationInterface::DOWN);
    }

    /**
     * Data provider for `::testAdapterRetrieval()`
     */
    public function goodAdapterProvider()
    {
        return array(
            array(
                'mysql',
                'Phinx\\Db\\Adapter\\MysqlAdapter',
            ),
            array(
                'pgsql',
                'Phinx\\Db\\Adapter\\PostgresAdapter',
            ),
            array(
                'sqlite',
                'Phinx\\Db\\Adapter\\SQLiteAdapter',
            ),
            array(
                'sqlsrv',
                'Phinx\\Db\\Adapter\\SqlServerAdapter',
            ),
            array(
                'custom',
                'Phinx\\Db\\Adapter\\MysqlAdapter', //Using Mysql adapter in place of a 'custom' adapter.
            ),
        );
    }

    /**
     * @dataProvider goodAdapterProvider
     */
    public function testAdapterFactories($adapterName, $adapterClass)
    {
        $env = new Environment('test-'.$adapterName, array());
        $env->registerAdapter('custom', function (Environment $env) {
            return new MysqlAdapter($env->getOptions(), $env->getOutput());
        });
        $env->setOptions(array('adapter' => $adapterName));
        $this->assertInstanceOf($adapterClass, $env->getAdapter(), 'Expected adapter provided to be instance of '.$adapterClass.'.');
    }

    /**
     * Data provider for `::testBadAdapterCalls()`
     */
    public function badAdapterProvider()
    {
        return array(
            array(
                'not-set',
                null,
            ),
            array(
                'not-callable',
                'not callable',
            ),
            array(
                'bad-adapter',
                function(){
                    return 'not an AdapterInterface';
                },
            ),
        );
    }

    /**
     * @dataProvider badAdapterProvider
     * @expectedException \RuntimeException
     */
    public function testBadAdapterFactories($adapterName, $adapter)
    {
        $env = new Environment('test-'.$adapterName, array());
        $env->registerAdapter($adapterName, $adapter);
        $env->setOptions(array('adapter' => $adapterName));
        $env->getAdapter();
    }
}
