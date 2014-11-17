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
    
    public function setUp()
    {
        $this->adapter = new TablePrefixAdapter(array(
            'table_prefix' => 'pre_',
            'table_suffix' => '_suf'
            ));
    }
    
    protected function getMockAdapter() {
        return $this->getMock('\Phinx\Db\Adapter\PdoAdapter', array(), array(array()));
    }
    
    public function tearDown()
    {
        unset($this->adapter);
    }
    
    public function testGetAdapterTableName() {
        $tableName = $this->adapter->getAdapterTableName('table');    
        $this->assertEquals('pre_table_suf', $tableName);
    }
    
    public function testHasTable() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('hasTable')
             ->with($this->equalTo('pre_table_suf'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->hasTable('table');
    }
    
    public function testCreateTable() {
        $table = new Table('table');
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('createTable')
             ->with($this->callback(function($table) {
                 return $table->getName() == 'pre_table_suf';
             }));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->createTable($table);
    }
    
    public function testRenameTable() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('renameTable')
             ->with($this->equalTo('pre_old_suf'),
                    $this->equalTo('pre_new_suf'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->renameTable('old', 'new');
    }
    
    public function testDropTable() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('dropTable')
             ->with($this->equalTo('pre_table_suf'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->dropTable('table');
    }
    
    public function testGetColumns() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('getColumns')
             ->with($this->equalTo('pre_table_suf'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->getColumns('table');
    }
    
    public function testHasColumn() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('hasColumn')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo('column'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->hasColumn('table', 'column');
    }
    
    public function testAddColumn() {
        $table = new Table('table');
        $column = new Column();
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('addColumn')
             ->with($this->callback(function($table) {
                 return $table->getName() == 'pre_table_suf';
             }, $this->equalTo($column)));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->addColumn($table, $column);
    }
    
    public function testRenameColumn() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('renameColumn')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo('column'),
                    $this->equalTo('new_column'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->renameColumn('table', 'column', 'new_column');
    }
    
    public function testChangeColumn() {
        $newColumn = new Column();
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('changeColumn')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo('column'),
                    $this->equalTo($newColumn));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->changeColumn('table', 'column', $newColumn);
    }
    
    public function testDropColumn() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('dropColumn')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo('column'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->dropColumn('table', 'column');
    }
    
    public function testHasIndex() {
        $columns = array();
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('hasIndex')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo($columns));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->hasIndex('table', $columns);
    }
    
    public function testDropIndex() {
        $columns = array();
        $options = null;
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('dropIndex')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo($columns),
                    $this->equalTo($options));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->dropIndex('table', $columns, $options);
    }
    
    public function testDropIndexByName() {
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('dropIndexByName')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo('index'));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->dropIndexByName('table', 'index');
    }
    
    public function testHasForeignKey() {
        $columns = array();
        $constraint = null;
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('hasForeignKey')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo($columns),
                    $this->equalTo($constraint));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->hasForeignKey('table', $columns, $constraint);
    }
    
    public function testAddForeignKey() {
        $table = new Table('table');
        $foreignKey = new ForeignKey();
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('addForeignKey')
             ->with($this->callback(function($table) {
                 return $table->getName() == 'pre_table_suf';
             }, $this->equalTo($foreignKey)));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->addForeignKey($table, $foreignKey);
    }
    
    public function testDropForeignKey() {
        $columns = array();
        $constraint = null;
        
        $mock = $this->getMockAdapter();
        $mock->expects($this->once())
             ->method('dropForeignKey')
             ->with($this->equalTo('pre_table_suf'),
                    $this->equalTo($columns),
                    $this->equalTo($constraint));
        $this->adapter->setAdapter($mock);
        
        $this->adapter->dropForeignKey('table', $columns, $constraint);
    }
}
