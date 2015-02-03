<?php

namespace Test\Phinx\Db;

use Phinx\Db\Adapter\MysqlAdapter;

class TableTest extends \PHPUnit_Framework_TestCase
{
    public function testAddColumnWithNoAdapterSpecified()
    {
        try {
            $table = new \Phinx\Db\Table('ntable');
            $table->addColumn('realname', 'string');
            $this->fail('Expected the table object to throw an exception');
        } catch (\RuntimeException $e) {
            $this->assertInstanceOf(
                'RuntimeException',
                $e,
                'Expected exception of type RuntimeException, got ' . get_class($e)
            );
            $this->assertRegExp('/An adapter must be specified to add a column./', $e->getMessage());
        }
    }

    public function testAddColumnWithColumnObject()
    {
        $adapter = new MysqlAdapter(array());
        $column = new \Phinx\Db\Table\Column();
        $column->setName('email')
               ->setType('integer');
        $table = new \Phinx\Db\Table('ntable', array(), $adapter);
        $table->addColumn($column);
        $columns = $table->getPendingColumns();
        $this->assertEquals('email', $columns[0]->getName());
        $this->assertEquals('integer', $columns[0]->getType());
    }

    public function testAddColumnWithAnInvalidColumnType()
    {
        try {
            $adapter = new MysqlAdapter(array());
            $column = new \Phinx\Db\Table\Column();
            $column->setType('badtype');
            $table = new \Phinx\Db\Table('ntable', array(), $adapter);
            $table->addColumn($column);
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertRegExp('/^An invalid column type /', $e->getMessage());
        }
    }

    public function testRemoveColumn()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('dropColumn');
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->removeColumn('test');
    }

    public function testRenameColumn()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('renameColumn');
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->renameColumn('test1', 'test2');
    }

    public function testChangeColumn()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('changeColumn');
        $newColumn = new \Phinx\Db\Table\Column();
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->changeColumn('test1', $newColumn);
    }

    public function testChangeColumnWithoutAColumnObject()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('changeColumn');
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->changeColumn('test1', 'text', array('null' => false));
    }

    public function testGetColumns()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('getColumns');

        $table = new \Phinx\Db\Table('table1', array(), $adapterStub);
        $table->getColumns();
    }

    public function testAddIndex()
    {
        $adapter = new MysqlAdapter(array());
        $table = new \Phinx\Db\Table('ntable', array(), $adapter);
        $table->addIndex(array('email'), array('unique' => true, 'name' => 'myemailindex'));
        $indexes = $table->getIndexes();
        $this->assertEquals(\Phinx\Db\Table\Index::UNIQUE, $indexes[0]->getType());
        $this->assertEquals('myemailindex', $indexes[0]->getName());
        $this->assertContains('email', $indexes[0]->getColumns());
    }

    public function testAddIndexWithoutType()
    {
        $adapter = new MysqlAdapter(array());
        $table = new \Phinx\Db\Table('ntable', array(), $adapter);
        $table->addIndex(array('email'));
        $indexes = $table->getIndexes();
        $this->assertEquals(\Phinx\Db\Table\Index::INDEX, $indexes[0]->getType());
        $this->assertContains('email', $indexes[0]->getColumns());
    }

    public function testAddIndexWithIndexObject()
    {
        $adapter = new MysqlAdapter(array());
        $index = new \Phinx\Db\Table\Index();
        $index->setType(\Phinx\Db\Table\Index::INDEX)
              ->setColumns(array('email'));
        $table = new \Phinx\Db\Table('ntable', array(), $adapter);
        $table->addIndex($index);
        $indexes = $table->getIndexes();
        $this->assertEquals(\Phinx\Db\Table\Index::INDEX, $indexes[0]->getType());
        $this->assertContains('email', $indexes[0]->getColumns());
    }

    public function testRemoveIndex()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('dropIndex');
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->removeIndex(array('email'));
    }

    public function testRemoveIndexByName()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('dropIndexByName');
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->removeIndexByName('emailindex');
    }

    public function testAddForeignKey()
    {
        $adapter = new MysqlAdapter(array());
        $table = new \Phinx\Db\Table('ntable', array(), $adapter);
        $table->addForeignKey('test', 'testTable', 'testRef');
        $fks = $table->getForeignKeys();
        $this->assertCount(1, $fks);
        $this->assertContains('test', $fks[0]->getColumns());
        $this->assertContains('testRef', $fks[0]->getReferencedColumns());
        $this->assertEquals('testTable', $fks[0]->getReferencedTable()->getName());
    }

    public function testDropForeignKey()
    {
        // stub adapter
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $adapterStub->expects($this->once())
                    ->method('dropForeignKey');
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $table->dropForeignKey('test');
    }

    public function testAddTimestamps()
    {
        $adapter = new MysqlAdapter(array());
        $table = new \Phinx\Db\Table('ntable', array(), $adapter);
        $table->addTimestamps();

        $columns = $table->getPendingColumns();

        $this->assertEquals('created_at', $columns[0]->getName());
        $this->assertEquals('timestamp', $columns[0]->getType());

        $this->assertEquals('updated_at', $columns[1]->getName());
        $this->assertEquals('timestamp', $columns[1]->getType());
        $this->assertTrue($columns[1]->isNull());
        $this->assertNull($columns[1]->getDefault());
    }

    public function testInsert()
    {
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $columns = array("column1", "column2");
        $data = array( array("value1", "value2") );
        $table->insert($columns, $data);
        $expectedData = array(
            array("columns" => $columns, "data" => $data)
        );
        $this->assertEquals($expectedData, $table->getData());
    }

    public function testInsertSaveData()
    {
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));

        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $columns = array("column1");
        $data = array(
            array("value1"),
            array("value2")
        );
        $moreData = array(
            array("value3"),
            array("value4")
        );

        $adapterStub->expects($this->exactly(2))
            ->method('insert')
            ->with($table, $columns, $this->logicalOr($data, $moreData));

        $table->insert($columns, $data)
            ->insert($columns, $moreData)
            ->save();
    }

    public function testResetAfterAddingData()
    {
        $adapterStub = $this->getMock('\Phinx\Db\Adapter\MysqlAdapter', array(), array(array()));
        $table = new \Phinx\Db\Table('ntable', array(), $adapterStub);
        $columns = array("column1");
        $data = array(array("value1"));
        $table->insert($columns, $data)->save();
        $this->assertEquals(array(), $table->getData());
    }
}
