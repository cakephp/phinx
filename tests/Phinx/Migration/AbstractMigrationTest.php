<?php
declare(strict_types=1);

namespace Test\Phinx\Migration;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractMigrationTest extends TestCase
{
    public function testAdapterMethods()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        // test methods
        $this->assertNull($migrationStub->getAdapter());
        $migrationStub->setAdapter($adapterStub);
        $this->assertInstanceOf(
            'Phinx\Db\Adapter\AdapterInterface',
            $migrationStub->getAdapter()
        );
    }

    public function testGetEnvironment()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);
        $this->assertEquals('mockenv', $migrationStub->getEnvironment());
    }

    public function testSetOutputMethods()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub output
        $outputStub = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')
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
            ->getMock();

        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0, $inputStub, null]);

        // test methods
        $this->assertNotNull($migrationStub->getInput());
        $this->assertInstanceOf('\Symfony\Component\Console\Input\InputInterface', $migrationStub->getInput());
    }

    public function testGetOutputMethodWithInjectedOutput()
    {
        // stub output
        $outputStub = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')
            ->getMock();

        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0, null, $outputStub]);

        // test methods
        $this->assertNotNull($migrationStub->getOutput());
        $this->assertInstanceOf('\Symfony\Component\Console\Output\OutputInterface', $migrationStub->getOutput());
    }

    public function testGetName()
    {
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);
        $this->assertStringContainsString('AbstractMigration', $migrationStub->getName());
    }

    public function testVersionMethods()
    {
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 20120103080000]);
        $this->assertEquals(20120103080000, $migrationStub->getVersion());
        $migrationStub->setVersion(20120915093312);
        $this->assertEquals(20120915093312, $migrationStub->getVersion());
    }

    public function testExecute()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('execute')
                    ->will($this->returnValue(2));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals(2, $migrationStub->execute('SELECT FOO FROM BAR'));
    }

    public function testQuery()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('query')
                    ->will($this->returnValue([['0' => 'bar', 'foo' => 'bar']]));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals([['0' => 'bar', 'foo' => 'bar']], $migrationStub->query('SELECT FOO FROM BAR'));
    }

    public function testFetchRow()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('fetchRow')
                    ->will($this->returnValue(['0' => 'bar', 'foo' => 'bar']));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals(['0' => 'bar', 'foo' => 'bar'], $migrationStub->fetchRow('SELECT FOO FROM BAR'));
    }

    public function testFetchAll()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('fetchAll')
                    ->will($this->returnValue([['0' => 'bar', 'foo' => 'bar']]));

        $migrationStub->setAdapter($adapterStub);
        $this->assertEquals([['0' => 'bar', 'foo' => 'bar']], $migrationStub->fetchAll('SELECT FOO FROM BAR'));
    }

    public function testCreateDatabase()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('createDatabase')
                    ->will($this->returnValue([['0' => 'bar', 'foo' => 'bar']]));

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->createDatabase('testdb', []);
    }

    public function testDropDatabase()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('dropDatabase')
                    ->will($this->returnValue([['0' => 'bar', 'foo' => 'bar']]));

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->dropDatabase('testdb');
    }

    public function testCreateSchema()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('createSchema')
                    ->with('testschema');

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->createSchema('testschema');
    }

    public function testDropSchema()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('dropSchema')
                    ->with('testschema');

        $migrationStub->setAdapter($adapterStub);
        $migrationStub->dropSchema('testschema');
    }

    public function testHasTable()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->once())
                    ->method('hasTable')
                    ->will($this->returnValue(true));

        $migrationStub->setAdapter($adapterStub);
        $this->assertTrue($migrationStub->hasTable('test_table'));
    }

    public function testTableMethod()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $migrationStub->setAdapter($adapterStub);

        $this->assertInstanceOf(
            'Phinx\Db\Table',
            $migrationStub->table('test_table')
        );
    }

    public function testPostFlightCheckFail()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->any())
            ->method('isValidColumnType')
            ->willReturn(true);

        $migrationStub->setAdapter($adapterStub);

        $table = $migrationStub->table('test_table');
        $table->addColumn('column1', 'integer', ['null' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration has pending actions after execution!');

        $migrationStub->postFlightCheck();
    }

    public function testPostFlightCheckSuccess()
    {
        // stub migration
        $migrationStub = $this->getMockForAbstractClass('\Phinx\Migration\AbstractMigration', ['mockenv', 0]);

        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->any())
            ->method('isValidColumnType')
            ->willReturn(true);

        $migrationStub->setAdapter($adapterStub);

        $table = $migrationStub->table('test_table');
        $table->addColumn('column1', 'integer', ['null' => true])->create();

        $migrationStub->postFlightCheck();

        // Dummy assert to prevent the test being marked as risky
        $this->assertTrue(true);
    }
}
