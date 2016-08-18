<?php

namespace Test\Phinx\Db;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\PostgresAdapter;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Adapter\SqlServerAdapter;

class TableTest extends \PHPUnit_Framework_TestCase
{
    public function provideTimestampColumnNames()
    {
        $result = [];
        $adapters = array_filter(
            [
                TESTS_PHINX_DB_ADAPTER_SQLSRV_ENABLED ? new SqlServerAdapter([]) : false,
                TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED ? new MysqlAdapter([]) : false,
                TESTS_PHINX_DB_ADAPTER_POSTGRES_ENABLED ? new PostgresAdapter([]) : false,
                TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED ? new SQLiteAdapter([]) : false,
            ]
        );
        foreach ($adapters as $adapter) {
            $result = array_merge(
                $result,
                [
                    [$adapter, null, null, 'created_at', 'updated_at'],
                    [$adapter, 'created_at', 'updated_at', 'created_at', 'updated_at'],
                    [$adapter, 'created', 'updated', 'created', 'updated'],
                    [$adapter, null, 'amendment_date', 'created_at', 'amendment_date'],
                    [$adapter, 'insertion_date', null, 'insertion_date', 'updated_at'],
                ]
            );
        }

        return $result;
    }

    public function testAddColumnWithAnInvalidColumnType()
    {
        try {
            $adapter = new MysqlAdapter([]);
            $column = new \Phinx\Db\Table\Column();
            $column->setType('badtype');
            $table = new \Phinx\Db\Table('ntable', [], $adapter);
            $table->addColumn($column);
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got '.get_class($e)
            );
            $this->assertRegExp('/^An invalid column type /', $e->getMessage());
        }
    }

    public function testAddColumnWithColumnObject()
    {
        $adapter = new MysqlAdapter([]);
        $column = new \Phinx\Db\Table\Column();
        $column->setName('email')
               ->setType('integer');
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addColumn($column);
        $columns = $table->getPendingColumns();
        $this->assertEquals('email', $columns[0]->getName());
        $this->assertEquals('integer', $columns[0]->getType());
    }

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
                'Expected exception of type RuntimeException, got '.get_class($e)
            );
            $this->assertRegExp('/An adapter must be specified to add a column./', $e->getMessage());
        }
    }

    public function testAddComment()
    {
        $adapter = new MysqlAdapter([]);
        $table = new \Phinx\Db\Table('ntable', ['comment' => 'test comment'], $adapter);
        $options = $table->getOptions();
        $this->assertEquals('test comment', $options['comment']);
    }

    public function testAddForeignKey()
    {
        $adapter = new MysqlAdapter([]);
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addForeignKey('test', 'testTable', 'testRef');
        $fks = $table->getForeignKeys();
        $this->assertCount(1, $fks);
        $this->assertContains('test', $fks[0]->getColumns());
        $this->assertContains('testRef', $fks[0]->getReferencedColumns());
        $this->assertEquals('testTable', $fks[0]->getReferencedTable()->getName());
    }

    public function testAddIndex()
    {
        $adapter = new MysqlAdapter([]);
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addIndex(['email'], ['unique' => true, 'name' => 'myemailindex']);
        $indexes = $table->getIndexes();
        $this->assertEquals(\Phinx\Db\Table\Index::UNIQUE, $indexes[0]->getType());
        $this->assertEquals('myemailindex', $indexes[0]->getName());
        $this->assertContains('email', $indexes[0]->getColumns());
    }

    public function testAddIndexWithIndexObject()
    {
        $adapter = new MysqlAdapter([]);
        $index = new \Phinx\Db\Table\Index();
        $index->setType(\Phinx\Db\Table\Index::INDEX)
              ->setColumns(['email']);
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addIndex($index);
        $indexes = $table->getIndexes();
        $this->assertEquals(\Phinx\Db\Table\Index::INDEX, $indexes[0]->getType());
        $this->assertContains('email', $indexes[0]->getColumns());
    }

    public function testAddIndexWithoutType()
    {
        $adapter = new MysqlAdapter([]);
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addIndex(['email']);
        $indexes = $table->getIndexes();
        $this->assertEquals(\Phinx\Db\Table\Index::INDEX, $indexes[0]->getType());
        $this->assertContains('email', $indexes[0]->getColumns());
    }

    /**
     * @dataProvider provideTimestampColumnNames
     *
     * @param AdapterInterface $adapter
     * @param string|null      $createdAtColumnName
     * @param string|null      $updatedAtColumnName
     * @param string           $expectedCreatedAtColumnName
     * @param string           $expectedUpdatedAtColumnName
     */
    public function testAddTimestamps(AdapterInterface $adapter, $createdAtColumnName, $updatedAtColumnName, $expectedCreatedAtColumnName, $expectedUpdatedAtColumnName)
    {
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addTimestamps($createdAtColumnName, $updatedAtColumnName);

        $columns = $table->getPendingColumns();

        $this->assertEquals($expectedCreatedAtColumnName, $columns[0]->getName());
        $this->assertEquals('timestamp', $columns[0]->getType());
        $this->assertEquals('CURRENT_TIMESTAMP', $columns[0]->getDefault());
        $this->assertEquals('', $columns[0]->getUpdate());

        $this->assertEquals($expectedUpdatedAtColumnName, $columns[1]->getName());
        $this->assertEquals('timestamp', $columns[1]->getType());
        $this->assertEquals('', $columns[1]->getUpdate());
        $this->assertTrue($columns[1]->isNull());
        $this->assertNull($columns[1]->getDefault());
    }

    public function testChangeColumn()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('changeColumn');
        $newColumn = new \Phinx\Db\Table\Column();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->changeColumn('test1', $newColumn);
    }

    public function testChangeColumnWithoutAColumnObject()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('changeColumn');
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->changeColumn('test1', 'text', ['null' => false]);
    }

    public function testDropForeignKey()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('dropForeignKey');
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->dropForeignKey('test');
    }

    public function testGetColumns()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('getColumns');

        $table = new \Phinx\Db\Table('table1', [], $adapterStub);
        $table->getColumns();
    }

    public function testInsert()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $data = [
            'column1' => 'value1',
            'column2' => 'value2',
        ];
        $table->insert($data);
        $expectedData = [
            $data,
        ];
        $this->assertEquals($expectedData, $table->getData());
    }

    public function testInsertSaveData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $data = [
            [
                'column1' => 'value1',
            ],
            [
                'column1' => 'value2',
            ],
        ];

        $moreData = [
            [
                'column1' => 'value3',
            ],
            [
                'column1' => 'value4',
            ],
        ];

        $adapterStub->expects($this->exactly(4))
                    ->method('insert')
                    ->with($table, $this->logicalOr($data[0], $data[1], $moreData[0], $moreData[1]));

        $table->insert($data)
              ->insert($moreData)
              ->save();
    }

    public function testRemoveColumn()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('dropColumn');
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->removeColumn('test');
    }

    public function testRemoveIndex()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('dropIndex');
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->removeIndex(['email']);
    }

    public function testRemoveIndexByName()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('dropIndexByName');
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->removeIndexByName('emailindex');
    }

    public function testRenameColumn()
    {
        // stub adapter
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $adapterStub->expects($this->once())
                    ->method('renameColumn');
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->renameColumn('test1', 'test2');
    }

    public function testResetAfterAddingData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
			->setConstructorArgs(array(array()))
			->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $columns = ["column1"];
        $data = [["value1"]];
        $table->insert($columns, $data)->save();
        $this->assertEquals([], $table->getData());
    }
}
