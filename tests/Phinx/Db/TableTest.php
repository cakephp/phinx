<?php

namespace Test\Phinx\Db;

use Phinx\Db\Action\DropIndex;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\PostgresAdapter;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Adapter\SqlServerAdapter;
use Phinx\Db\Table\Index;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
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
                    [$adapter, null, null, 'created_at', 'updated_at', false],
                    [$adapter, 'created_at', 'updated_at', 'created_at', 'updated_at', true],
                    [$adapter, 'created', 'updated', 'created', 'updated', false],
                    [$adapter, null, 'amendment_date', 'created_at', 'amendment_date', true],
                    [$adapter, 'insertion_date', null, 'insertion_date', 'updated_at', true],
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
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
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
        $actions = $this->getPendingActions($table);
        $this->assertInstanceOf('Phinx\Db\Action\AddColumn', $actions[0]);
        $this->assertSame($column, $actions[0]->getColumn());
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
                'Expected exception of type RuntimeException, got ' . get_class($e)
            );
        }
    }

    public function testAddComment()
    {
        $adapter = new MysqlAdapter([]);
        $table = new \Phinx\Db\Table('ntable', ['comment' => 'test comment'], $adapter);
        $options = $table->getOptions();
        $this->assertEquals('test comment', $options['comment']);
    }

    public function testAddIndexWithIndexObject()
    {
        $adapter = new MysqlAdapter([]);
        $index = new \Phinx\Db\Table\Index();
        $index->setType(\Phinx\Db\Table\Index::INDEX)
              ->setColumns(['email']);
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addIndex($index);
        $actions = $this->getPendingActions($table);
        $this->assertInstanceOf('Phinx\Db\Action\AddIndex', $actions[0]);
        $this->assertSame($index, $actions[0]->getIndex());
    }

    /**
     * @dataProvider provideTimestampColumnNames
     *
     * @param AdapterInterface $adapter
     * @param string|null      $createdAtColumnName
     * @param string|null      $updatedAtColumnName
     * @param string           $expectedCreatedAtColumnName
     * @param string           $expectedUpdatedAtColumnName
     * @param boolean          $withTimezone
     */
    public function testAddTimestamps(AdapterInterface $adapter, $createdAtColumnName, $updatedAtColumnName, $expectedCreatedAtColumnName, $expectedUpdatedAtColumnName, $withTimezone)
    {
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addTimestamps($createdAtColumnName, $updatedAtColumnName, $withTimezone);
        $actions = $this->getPendingActions($table);

        $columns = [];

        foreach ($actions as $action) {
            $columns[] = $action->getColumn();
        }

        $this->assertEquals($expectedCreatedAtColumnName, $columns[0]->getName());
        $this->assertEquals('timestamp', $columns[0]->getType());
        $this->assertEquals('CURRENT_TIMESTAMP', $columns[0]->getDefault());
        $this->assertEquals($withTimezone, $columns[0]->getTimezone());
        $this->assertEquals('', $columns[0]->getUpdate());

        $this->assertEquals($expectedUpdatedAtColumnName, $columns[1]->getName());
        $this->assertEquals('timestamp', $columns[1]->getType());
        $this->assertEquals($withTimezone, $columns[1]->getTimezone());
        $this->assertEquals('', $columns[1]->getUpdate());
        $this->assertTrue($columns[1]->isNull());
        $this->assertNull($columns[1]->getDefault());
    }

    /**
     * @dataProvider provideTimestampColumnNames
     *
     * @param AdapterInterface $adapter
     * @param string|null      $createdAtColumnName
     * @param string|null      $updatedAtColumnName
     * @param string           $expectedCreatedAtColumnName
     * @param string           $expectedUpdatedAtColumnName
     * @param boolean          $withTimezone
     */
    public function testAddTimestampsWithTimezone(AdapterInterface $adapter, $createdAtColumnName, $updatedAtColumnName, $expectedCreatedAtColumnName, $expectedUpdatedAtColumnName, $withTimezone)
    {
        $table = new \Phinx\Db\Table('ntable', [], $adapter);
        $table->addTimestampsWithTimezone($createdAtColumnName, $updatedAtColumnName);
        $actions = $this->getPendingActions($table);

        $columns = [];

        foreach ($actions as $action) {
            $columns[] = $action->getColumn();
        }

        $this->assertEquals($expectedCreatedAtColumnName, $columns[0]->getName());
        $this->assertEquals('timestamp', $columns[0]->getType());
        $this->assertEquals('CURRENT_TIMESTAMP', $columns[0]->getDefault());
        $this->assertEquals(true, $columns[0]->getTimezone());
        $this->assertEquals('', $columns[0]->getUpdate());

        $this->assertEquals($expectedUpdatedAtColumnName, $columns[1]->getName());
        $this->assertEquals('timestamp', $columns[1]->getType());
        $this->assertEquals(true, $columns[1]->getTimezone());
        $this->assertEquals('', $columns[1]->getUpdate());
        $this->assertTrue($columns[1]->isNull());
        $this->assertNull($columns[1]->getDefault());
    }

    public function testInsert()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
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

    public function testInsertMultipleRowsWithoutZeroKey()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $data = [
            1 => [
                'column1' => 'value1',
                'column2' => 'value2',
            ],
            2 => [
                'column1' => 'value1',
                'column2' => 'value2',
            ]
        ];
        $table->insert($data);
        $expectedData = array_values($data);
        $this->assertEquals($expectedData, $table->getData());
    }

    public function testInsertSaveEmptyData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);

        $adapterStub->expects($this->never())->method('bulkinsert');

        $table->insert([])->save();
    }

    public function testInsertSaveData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
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

        $adapterStub->expects($this->exactly(1))
                    ->method('bulkinsert')
                    ->with($table->getTable(), [$data[0], $data[1], $moreData[0], $moreData[1]]);

        $table->insert($data)
              ->insert($moreData)
              ->save();
    }

    public function testSaveAfterSaveData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
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

        $adapterStub->expects($this->any())
            ->method('isValidColumnType')
            ->willReturn(true);
        $adapterStub->expects($this->exactly(1))
            ->method('bulkinsert')
            ->with($table->getTable(), [$data[0], $data[1]]);

        $table
            ->addColumn('column1', 'string', ['null' => true])
            ->save();
        $table
            ->insert($data)
            ->saveData();
        $table
            ->changeColumn('column1', 'string', ['null' => false])
            ->save();
    }

    public function testResetAfterAddingData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $columns = ["column1"];
        $data = [["value1"]];
        $table->insert($columns, $data)->save();
        $this->assertEquals([], $table->getData());
    }

    public function testPendingAfterAddingData()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $columns = ["column1"];
        $data = [["value1"]];
        $table->insert($columns, $data);
        $this->assertEquals(true, $table->hasPendingActions());
    }

    public function testPendingAfterAddingColumn()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();
        $adapterStub->expects($this->any())
            ->method('isValidColumnType')
            ->willReturn(true);
        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);
        $table->addColumn("column1", "integer", ['null' => true]);
        $this->assertEquals(true, $table->hasPendingActions());
    }

    public function testGetColumn()
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        $column1 = (new \Phinx\Db\Table\Column())->setName('column1');

        $adapterStub->expects($this->exactly(2))
            ->method('getColumns')
            ->willReturn([
                $column1
            ]);

        $table = new \Phinx\Db\Table('ntable', [], $adapterStub);

        $this->assertEquals($column1, $table->getColumn('column1'));
        $this->assertNull($table->getColumn('column2'));
    }

    /**
     * @dataProvider removeIndexDataprovider
     *
     * @param string $indexIdentifier
     * @param Index $index
     */
    public function testRemoveIndex($indexIdentifier, Index $index)
    {
        $adapterStub = $this->getMockBuilder('\Phinx\Db\Adapter\MysqlAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        $table = new \Phinx\Db\Table('table', [], $adapterStub);
        $table->removeIndex($indexIdentifier);

        $indexes = array_map(function (DropIndex $action) {
            return $action->getIndex();
        }, $this->getPendingActions($table));

        $this->assertEquals([$index], $indexes);
    }

    public function removeIndexDataprovider()
    {
        return [
            [
                'indexA',
                (new Index())->setColumns(['indexA'])
            ],
            [
                ['indexB', 'indexC'],
                (new Index())->setColumns(['indexB', 'indexC'])
            ],
            [
                ['indexD'],
                (new Index())->setColumns(['indexD'])
            ]
        ];
    }

    protected function getPendingActions($table)
    {
        $prop = new \ReflectionProperty(get_class($table), 'actions');
        $prop->setAccessible(true);

        return $prop->getValue($table)->getActions();
    }
}
