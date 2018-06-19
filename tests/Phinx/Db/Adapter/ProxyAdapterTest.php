<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\ProxyAdapter;
use PHPUnit\Framework\TestCase;

class ProxyAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\ProxyAdapter
     */
    private $adapter;

    public function setUp()
    {
        $stub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->setMethods([])
            ->getMock();

        $stub->expects($this->any())
            ->method('isValidColumnType')
            ->will($this->returnValue(true));

        $this->adapter = new ProxyAdapter($stub);
    }

    public function tearDown()
    {
        unset($this->adapter);
    }

    public function testProxyAdapterCanInvertCreateTable()
    {
        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\DropTable', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
    }

    public function testProxyAdapterCanInvertRenameTable()
    {
        $table = new \Phinx\Db\Table('oldname', [], $this->adapter);
        $table->rename('newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\RenameTable', $commands[0]);
        $this->assertEquals('newname', $commands[0]->getTable()->getName());
        $this->assertEquals('oldname', $commands[0]->getNewName());
    }

    public function testProxyAdapterCanInvertAddColumn()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));
        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addColumn('acolumn', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\RemoveColumn', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals('acolumn', $commands[0]->getColumn()->getName());
    }

    public function testProxyAdapterCanInvertRenameColumn()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->renameColumn('oldname', 'newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\RenameColumn', $commands[0]);
        $this->assertEquals('newname', $commands[0]->getColumn()->getName());
        $this->assertEquals('oldname', $commands[0]->getNewName());
    }

    public function testProxyAdapterCanInvertAddIndex()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addIndex(['email'])
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\DropIndex', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals(['email'], $commands[0]->getIndex()->getColumns());
    }

    public function testProxyAdapterCanInvertAddForeignKey()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addForeignKey(['ref_table_id'], 'refTable')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\DropForeignKey', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals(['ref_table_id'], $commands[0]->getForeignKey()->getColumns());
    }

    /**
     * @expectedException \Phinx\Migration\IrreversibleMigrationException
     * @expectedExceptionMessage Cannot reverse a "Phinx\Db\Action\RemoveColumn" command
     */
    public function testGetInvertedCommandsThrowsExceptionForIrreversibleCommand()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->removeColumn('thing')
              ->save();
        $this->adapter->getInvertedCommands();
    }
}
