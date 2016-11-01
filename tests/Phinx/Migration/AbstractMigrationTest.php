<?php

namespace Test\Phinx\Migration;

use Phinx\Db\Table;
use Phinx\Db\Adapter\AdapterInterface;

class AbstractMigrationTest extends \PHPUnit_Framework_TestCase
{
    public function testUp()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));
        $this->assertNull($migrationStub->up());
    }

    public function testDown()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));
        $this->assertNull($migrationStub->down());
    }

    public function testAdapterMethods()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        // test methods
        $this->assertNull($migrationStub->getAdapter());
        $migrationStub->setAdapter($adapterStub);
        $this->assertInstanceOf(AdapterInterface::class, $migrationStub->getAdapter());
    }

    public function testSetOutputMethods()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub output
        $outputStub = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')
            ->disableOriginalConstructor()
            ->getMock();

        // test methods
        $this->assertNull($migrationStub->getOutput());
        $migrationStub->setOutput($outputStub);
        $this->assertInstanceOf('\Symfony\Component\Console\Output\OutputInterface', $migrationStub->getOutput());
    }

    public function testGetInputMethodWithInjectedInput()
    {
        // stub input
        $inputStub = $this->getMockBuilder('\Symfony\Component\Console\Input\InputInterface')
            ->disableOriginalConstructor()
            ->getMock();

        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0, $inputStub, null));

        // test methods
        $this->assertNotNull($migrationStub->getInput());
        $this->assertInstanceOf('\Symfony\Component\Console\Input\InputInterface', $migrationStub->getInput());
    }

    public function testGetOutputMethodWithInjectedOutput()
    {
        // stub output
        $outputStub = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')
            ->disableOriginalConstructor()
            ->getMock();

        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0, null, $outputStub));

        // test methods
        $this->assertNotNull($migrationStub->getOutput());
        $this->assertInstanceOf('\Symfony\Component\Console\Output\OutputInterface', $migrationStub->getOutput());
    }

    public function testGetName()
    {
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));
        $this->assertNotFalse(strpos($migrationStub->getName(), 'AbstractMigration'));
    }

    public function testVersionMethods()
    {
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(20120103080000));
        $this->assertEquals(20120103080000, $migrationStub->getVersion());
        $migrationStub->setVersion(20120915093312);
        $this->assertEquals(20120915093312, $migrationStub->getVersion());
    }

    public function testExecute()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();
        $adapterStub->expects(static::once())
                    ->method('execute')
                    ->will(static::returnValue(2));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals(2, $migrationStub->execute('SELECT FOO FROM BAR'));
    }

    public function testQuery()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();
        $adapterStub->expects(static::once())
                    ->method('query')
                    ->will(static::returnValue(array(array('0' => 'bar', 'foo' => 'bar'))));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals(array(array('0' => 'bar', 'foo' => 'bar')), $migrationStub->query('SELECT FOO FROM BAR'));
    }

    public function testFetchRow()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterStub->expects(static::once())
                    ->method('fetchRow')
                    ->will(static::returnValue(array('0' => 'bar', 'foo' => 'bar')));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals(array('0' => 'bar', 'foo' => 'bar'), $migrationStub->fetchRow('SELECT FOO FROM BAR'));
    }

    public function testFetchAll()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterStub->expects(static::once())
                    ->method('fetchAll')
                    ->will(static::returnValue(array(array('0' => 'bar', 'foo' => 'bar'))));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals(array(array('0' => 'bar', 'foo' => 'bar')), $migrationStub->fetchAll('SELECT FOO FROM BAR'));
    }

    public function testInsert()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterStub->expects(static::once())
                    ->method('insert');

        $table = new Table('testdb', [], $adapterStub);

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->insert($table, ['row' => 'value']);
    }

    public function testCreateDatabase()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterStub->expects(static::once())
                    ->method('createDatabase')
                    ->will(static::returnValue(array(array('0' => 'bar', 'foo' => 'bar'))));

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->createDatabase('testdb', array());
    }

    public function testDropDatabase()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterStub->expects(static::once())
                    ->method('dropDatabase')
                    ->will(static::returnValue(array(array('0' => 'bar', 'foo' => 'bar'))));

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->dropDatabase('testdb');
    }

    public function testHasTable()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterStub->expects(static::once())
                    ->method('hasTable')
                    ->will(static::returnValue(true));

        $migrationStub->setAdapter($adapterStub);
        $this->assertTrue($migrationStub->hasTable('test_table'));
    }

    public function testTableMethod()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $migrationStub->setAdapter($adapterStub);

        $this->assertInstanceOf(Table::class, $migrationStub->table('test_table'));
    }

    public function testDropTableMethod()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', array(0));

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->disableOriginalConstructor()
            ->getMock();
        $adapterStub->expects(static::once())
                    ->method('dropTable');

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->dropTable('test_table');
    }
}
