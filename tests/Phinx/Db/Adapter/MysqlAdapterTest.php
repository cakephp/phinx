<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\MysqlAdapter;

class MysqlAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\MysqlAdapter
     */
    private $adapter;
    
    public function setUp()
    {
        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT
        );
        $this->adapter = new MysqlAdapter($options);
        
        // ensure the database is empty for each test
        $tables = $this->adapter->fetchAll('SHOW TABLES');
        foreach ($tables as $table) {
            $this->adapter->dropTable($table[0]);
        }
    }
    
    public function tearDown()
    {
        unset($this->adapter);
    }
    
    public function testConnection()
    {
        $this->assertTrue($this->adapter->getConnection() instanceof \PDO);
    }
    
    public function testConnectionWithoutPort()
    {
        $options = $this->adapter->getOptions();
        unset($options['port']);
        $this->adapter->setOptions($options);
        $this->assertTrue($this->adapter->getConnection() instanceof \PDO);
    }
    
    public function testConnectionWithInvalidCredentials()
    {
        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        );
        
        try {
            $adapter = new MysqlAdapter($options);
            $adapter->connect();
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e));
            $this->assertRegExp('/There was a problem connecting to the database/', $e->getMessage());
        }
    }
    
    public function testCreatingTheSchemaTableOnConnect()
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
        $this->assertFalse($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->disconnect();
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
    }
    
    public function testQuoteTableName()
    {
        $this->assertEquals('`test_table`', $this->adapter->quoteTableName('test_table'));
    }
    
    public function testQuoteColumnName()
    {
        $this->assertEquals('`test_column`', $this->adapter->quoteColumnName('test_column'));
    }
    
    public function testCreateTable()
    {
    }
    
    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }
    
    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }
    
    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        
        try {
            $this->adapter->renameColumn('t', 'column2', 'column1');
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e));
            $this->assertEquals('The specified column doesn\'t exist: column2', $e->getMessage());
        }
    }
    
    public function testDropColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->dropColumn('t', 'column1');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
    }
}