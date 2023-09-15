<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Action\AddColumn;
use Phinx\Db\Action\AddForeignKey;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\ChangeColumn;
use Phinx\Db\Action\ChangeComment;
use Phinx\Db\Action\ChangePrimaryKey;
use Phinx\Db\Action\DropForeignKey;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Action\RemoveColumn;
use Phinx\Db\Action\RenameColumn;
use Phinx\Db\Action\RenameTable;
use Phinx\Db\Adapter\TablePrefixAdapter;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Table as TableValue;
use PHPUnit\Framework\TestCase;

class TablePrefixAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\TablePrefixAdapter
     */
    private $adapter;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    private $mock;

    protected function setUp(): void
    {
        $options = [
            'table_prefix' => 'pre_',
            'table_suffix' => '_suf',
        ];

        $this->mock = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        $this->mock
            ->expects($this->any())
            ->method('getOption')
            ->with($this->logicalOr(
                $this->equalTo('table_prefix'),
                $this->equalTo('table_suffix')
            ))
            ->will($this->returnCallback(function ($option) use ($options) {
                return $options[$option];
            }));

        $this->mock
            ->expects($this->any())
            ->method('getColumnForType')
            ->will($this->returnCallback(function ($name, $type, $options) {
                $col = new Column();
                $col->setName($name);
                $col->setType($type);
                $col->setOptions($options);

                return $col;
            }));

        $this->adapter = new TablePrefixAdapter($this->mock);
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
        unset($this->mock);
    }

    public function testGetAdapterTableName()
    {
        $tableName = $this->adapter->getAdapterTableName('table');
        $this->assertEquals('pre_table_suf', $tableName);
    }

    public function testHasTable()
    {
        $this->mock
            ->expects($this->once())
            ->method('hasTable')
            ->with($this->equalTo('pre_table_suf'));

        $this->adapter->hasTable('table');
    }

    public function testCreateTable()
    {
        $table = new TableValue('table');

        $this->mock
            ->expects($this->once())
            ->method('createTable')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() === 'pre_table_suf';
                }
            ));

        $this->adapter->createTable($table);
    }

    public function testChangePrimaryKey()
    {
        $table = new TableValue('table');
        $newColumns = 'column1';

        $expectedTable = new TableValue('pre_table_suf');
        $this->mock
            ->expects($this->once())
            ->method('changePrimaryKey')
            ->with(
                $this->equalTo($expectedTable),
                $this->equalTo($newColumns)
            );

        $this->adapter->changePrimaryKey($table, $newColumns);
    }

    public function testChangeComment()
    {
        $table = new TableValue('table');
        $newComment = 'comment';

        $expectedTable = new TableValue('pre_table_suf');
        $this->mock
            ->expects($this->once())
            ->method('changeComment')
            ->with(
                $this->equalTo($expectedTable),
                $this->equalTo($newComment)
            );

        $this->adapter->changeComment($table, $newComment);
    }

    public function testRenameTable()
    {
        $this->mock
            ->expects($this->once())
            ->method('renameTable')
            ->with(
                $this->equalTo('pre_old_suf'),
                $this->equalTo('pre_new_suf')
            );

        $this->adapter->renameTable('old', 'new');
    }

    public function testDropTable()
    {
        $this->mock
            ->expects($this->once())
            ->method('dropTable')
            ->with($this->equalTo('pre_table_suf'));

        $this->adapter->dropTable('table');
    }

    public function testGetColumns()
    {
        $this->mock
            ->expects($this->once())
            ->method('getColumns')
            ->with($this->equalTo('pre_table_suf'));

        $this->adapter->getColumns('table');
    }

    public function testHasColumn()
    {
        $this->mock
            ->expects($this->once())
            ->method('hasColumn')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo('column')
            );

        $this->adapter->hasColumn('table', 'column');
    }

    public function testAddColumn()
    {
        $table = new TableValue('table');
        $column = new Column();

        $this->mock
            ->expects($this->once())
            ->method('addColumn')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() === 'pre_table_suf';
                },
                $this->equalTo($column)
            ));

        $this->adapter->addColumn($table, $column);
    }

    public function testRenameColumn()
    {
        $this->mock
            ->expects($this->once())
            ->method('renameColumn')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo('column'),
                $this->equalTo('new_column')
            );

        $this->adapter->renameColumn('table', 'column', 'new_column');
    }

    public function testChangeColumn()
    {
        $newColumn = new Column();

        $this->mock
            ->expects($this->once())
            ->method('changeColumn')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo('column'),
                $this->equalTo($newColumn)
            );

        $this->adapter->changeColumn('table', 'column', $newColumn);
    }

    public function testDropColumn()
    {
        $this->mock
            ->expects($this->once())
            ->method('dropColumn')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo('column')
            );

        $this->adapter->dropColumn('table', 'column');
    }

    public function testHasIndex()
    {
        $columns = [];

        $this->mock
            ->expects($this->once())
            ->method('hasIndex')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo($columns)
            );

        $this->adapter->hasIndex('table', $columns);
    }

    public function testDropIndex()
    {
        $columns = [];

        $this->mock
            ->expects($this->once())
            ->method('dropIndex')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo($columns)
            );

        $this->adapter->dropIndex('table', $columns);
    }

    public function testDropIndexByName()
    {
        $this->mock
            ->expects($this->once())
            ->method('dropIndexByName')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo('index')
            );

        $this->adapter->dropIndexByName('table', 'index');
    }

    public function testHasPrimaryKey()
    {
        $columns = [];
        $constraint = null;

        $this->mock
            ->expects($this->once())
            ->method('hasPrimaryKey')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo($columns),
                $this->equalTo($constraint)
            );

        $this->adapter->hasPrimaryKey('table', $columns, $constraint);
    }

    public function testHasForeignKey()
    {
        $columns = [];
        $constraint = null;

        $this->mock
            ->expects($this->once())
            ->method('hasForeignKey')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo($columns),
                $this->equalTo($constraint)
            );

        $this->adapter->hasForeignKey('table', $columns, $constraint);
    }

    public function testAddForeignKey()
    {
        $table = new TableValue('table');
        $foreignKey = new ForeignKey();

        $this->mock
            ->expects($this->once())
            ->method('addForeignKey')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() === 'pre_table_suf';
                },
                $this->equalTo($foreignKey)
            ));

        $this->adapter->addForeignKey($table, $foreignKey);
    }

    public function testDropForeignKey()
    {
        $columns = [];
        $constraint = null;

        $this->mock
            ->expects($this->once())
            ->method('dropForeignKey')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo($columns),
                $this->equalTo($constraint)
            );

        $this->adapter->dropForeignKey('table', $columns, $constraint);
    }

    public function testInsertData()
    {
        $row = ['column1' => 'value3'];

        $this->mock
            ->expects($this->once())
            ->method('bulkinsert')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() === 'pre_table_suf';
                },
                $this->equalTo($row)
            ));

        $table = new Table('table', [], $this->adapter);
        $table->insert($row)
              ->save();
    }

    public function actionsProvider()
    {
        $table = new TableValue('my_test');

        return [
            [AddColumn::build($table, 'acolumn', 'int')],
            [AddIndex::build($table, ['acolumn'])],
            [AddForeignKey::build($table, ['acolumn'], 'another_table'), true],
            [ChangeColumn::build($table, 'acolumn', 'int')],
            [DropForeignKey::build($table, ['acolumn'])],
            [DropIndex::build($table, ['acolumn'])],
            [new DropTable($table)],
            [RemoveColumn::build($table, 'acolumn')],
            [RenameColumn::build($table, 'acolumn', 'another')],
            [new RenameTable($table, 'new_name'), true],
            [new ChangePrimaryKey($table, 'column1')],
            [new ChangeComment($table, 'comment1')],
        ];
    }

    /**
     * @dataProvider actionsProvider
     */
    public function testExecuteActions($action, $checkReferecedTable = false)
    {
        $this->mock->expects($this->once())
            ->method('executeActions')
            ->will($this->returnCallback(function ($table, $newActions) use ($action, $checkReferecedTable) {
                $this->assertCount(1, $newActions);
                $this->assertSame(get_class($action), get_class($newActions[0]));
                $this->assertEquals('pre_my_test_suf', $newActions[0]->getTable()->getName());

                if ($checkReferecedTable) {
                    if ($action instanceof AddForeignKey) {
                        $this->assertEquals(
                            'pre_another_table_suf',
                            $newActions[0]->getForeignKey()->getReferencedTable()->getName()
                        );
                    } elseif ($action instanceof RenameTable) {
                        $this->assertEquals(
                            'pre_new_name_suf',
                            $newActions[0]->getNewName()
                        );
                    }
                }
            }));

        $table = new TableValue('my_test');
        $this->adapter->executeActions($table, [$action]);
    }
}
