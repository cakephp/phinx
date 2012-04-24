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
            $this->assertInstanceOf('RuntimeException', $e,
                'Expected exception of type RuntimeException, got ' . get_class($e));
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
        $columns = $table->getColumns();
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
            $this->assertInstanceOf('InvalidArgumentException', $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e));
            $this->assertRegExp('/An invalid column type was specified./', $e->getMessage());
        }
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
}