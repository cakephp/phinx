<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\TablePrefixAdapter;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;

class TablePrefixAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\TablePrefixAdapter
     */
    private $adapter;

    /**
     * @var \Phinx\Db\Adapter\AdapterInterface
     */
    private $mock;

    public function setUp()
    {
        $options = array(
            'table_prefix' => 'pre_',
            'table_suffix' => '_suf',
        );

        $this->mock = $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));

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

        $this->adapter = new TablePrefixAdapter($this->mock);
    }

    public function tearDown()
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
        $table = new Table('table');

        $this->mock
            ->expects($this->once())
            ->method('createTable')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() == 'pre_table_suf';
                }
            ));

        $this->adapter->createTable($table);
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
        $table = new Table('table');
        $column = new Column();

        $this->mock
            ->expects($this->once())
            ->method('addColumn')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() == 'pre_table_suf';
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
        $columns = array();

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
        $columns = array();
        $options = null;

        $this->mock
            ->expects($this->once())
            ->method('dropIndex')
            ->with(
                $this->equalTo('pre_table_suf'),
                $this->equalTo($columns),
                $this->equalTo($options)
            );

        $this->adapter->dropIndex('table', $columns, $options);
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

    public function testHasForeignKey()
    {
        $columns = array();
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
        $table = new Table('table');
        $foreignKey = new ForeignKey();

        $this->mock
            ->expects($this->once())
            ->method('addForeignKey')
            ->with($this->callback(
                function ($table) {
                    return $table->getName() == 'pre_table_suf';
                },
                $this->equalTo($foreignKey)
            ));

        $this->adapter->addForeignKey($table, $foreignKey);
    }

    public function testDropForeignKey()
    {
        $columns = array();
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

    public function testAddTableWithForeignKey()
    {
        $this->mock
            ->expects($this->any())
            ->method('isValidColumnType')
            ->with($this->callback(
                function ($column) {
                    return in_array($column->getType(), array('string', 'integer'));
                }
            ))
            ->will($this->returnValue(true));

        $table = new Table('table', array(), $this->adapter);
        $table
            ->addColumn('bar', 'string')
            ->addColumn('relation', 'integer')
            ->addForeignKey('relation', 'target_table', array('id'));

        $this->mock
            ->expects($this->once())
            ->method('createTable')
            ->with($this->callback(
                function ($table) {
                    if ($table->getName() !== 'pre_table_suf') {
                        throw new \Exception(sprintf(
                            'Table::getName was not prefixed/suffixed properly: "%s"',
                            $table->getName()
                        ));
                    }
                    $fks = $table->getForeignKeys();
                    if (count($fks) !== 1) {
                        throw new \Exception(sprintf(
                            'Table::getForeignKeys count was incorrect: %d',
                            count($fks)
                        ));
                    }
                    foreach ($fks as $fk) {
                        if ($fk->getReferencedTable()->getName() !== 'pre_target_table_suf') {
                            throw new \Exception(sprintf(
                                'ForeignKey::getReferencedTable was not prefixed/suffixed properly: "%s"',
                                $fk->getReferencedTable->getName()
                            ));
                        }
                    }
                    return true;
                }
            ));

        $table->create();
    }
}
