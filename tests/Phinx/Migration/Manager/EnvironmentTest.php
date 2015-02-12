<?php

namespace Test\Phinx\Migration\Manager;

use Phinx\Migration\Manager\Environment;
use Phinx\Migration\MigrationInterface;

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
     * @expectedExceptionMessage Adapter "fakeadapter" has not been registered
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
        $this->assertInstanceOf('Phinx\Db\Adapter\TablePrefixAdapter', $this->environment->getAdapter());
        
        $tablePrefixAdapter = $this->environment->getAdapter();
        $this->assertInstanceOf('Phinx\Db\Adapter\MysqlAdapter', $tablePrefixAdapter->getAdapter());
    }

    public function testSchemaName()
    {
        $this->assertEquals('phinxlog', $this->environment->getSchemaTableName());

        $this->environment->setSchemaTableName('changelog');
        $this->assertEquals('changelog', $this->environment->getSchemaTableName());
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

        $this->environment->executeMigration($upMigration, MigrationInterface::UP);
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

        $this->environment->executeMigration($downMigration, MigrationInterface::DOWN);
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

        $this->environment->executeMigration($migration, MigrationInterface::UP);
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

        $this->environment->executeMigration($migration, MigrationInterface::UP);
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

        $this->environment->executeMigration($migration, MigrationInterface::DOWN);
    }
}
