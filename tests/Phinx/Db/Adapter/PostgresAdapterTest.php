<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Output\NullOutput,
    Phinx\Db\Adapter\PostgresAdapter;

class PostgresAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\PostgresqlAdapter
     */
    private $adapter;
    
    public function setUp()
    {
        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_POSTGRES_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE, 
            'user' => TESTS_PHINX_DB_ADAPTER_POSTGRES_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PORT
        );
        $this->adapter = new PostgresAdapter($options, new NullOutput());        

        $this->adapter->dropAllSchemas();
        $this->adapter->createSchema('public');

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();        
    }
    
    public function tearDown()
    {
        $this->adapter->dropAllSchemas();
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
            'host' => TESTS_PHINX_DB_ADAPTER_POSTGRES_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        );
        
        try {
            $adapter = new PostgresAdapter($options, new NullOutput());
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
        $this->assertEquals('"test_table"', $this->adapter->quoteTableName('test_table'));
    }
    
    public function testQuoteColumnName()
    {
        $this->assertEquals('"test_column"', $this->adapter->quoteColumnName('test_column'));
    }
    
    public function testCreateTable()
    {
        $table = new \Phinx\Db\Table('ntable', array(), $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'ntable_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
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
    
    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
              ->save();
        $this->assertTrue($table->hasColumn('email'));
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', array('default' => 'test'))
              ->save();
        $rows = $this->adapter->fetchAll("SELECT column_default FROM information_schema.columns WHERE table_name ='table1'");
        $this->assertEquals("'test'::character varying", $rows[0]['column_default']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', array('default' => 0))
              ->save();
        $rows = $this->adapter->fetchAll("SELECT column_default FROM information_schema.columns WHERE table_name ='table1'");        
        $this->assertNotNull($rows[0]['column_default']);
        $this->assertEquals("0", $rows[0]['column_default']);
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

    public function testGetColumns()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->addColumn('column3', 'biginteger')
              ->addColumn('column4', 'text')
              ->addColumn('column5', 'float')
              ->addColumn('column6', 'decimal')              
              ->addColumn('column7', 'time')
              ->addColumn('column8', 'timestamp')
              ->addColumn('column9','date')
              ->addColumn('column10', 'boolean')
              ->addColumn('column11', 'string', array('limit' => 10));              
        $pendingColumns = $table->getPendingColumns();
        $table->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertCount(count($pendingColumns) + 1, $columns);
        for ($i = 0; $i++; $i < count($pendingColumns)) {
            $this->assertEquals($pendingColumns[$i], $columns[$i+1]);
        }
    }
    
    public function testAddIndex()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
    }
    
    public function testDropIndex()
    {
         // single column index
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));
        
        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', array(), $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(array('fname', 'lname'))
               ->save();
        $this->assertTrue($table2->hasIndex(array('fname', 'lname')));
        $this->adapter->dropIndex($table2->getName(), array('fname', 'lname'));     
        $this->assertFalse($table2->hasIndex(array('fname', 'lname')));       
    }

    public function testAddForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', array(), $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', array(), $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(array('ref_table_id'))
           ->setReferencedColumns(array('ref_table_id'));

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
    }

    public function dropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', array(), $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', array(), $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(array('ref_table_id'))
           ->setReferencedColumns(array('ref_table_id'));

        $this->adapter->addForeignKey($table, $fk);
        $this->adapter->dropForeignKey($table->getName(), array('ref_table_id'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
    }
    
    public function testHasDatabase()
    {                
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));        
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE));        
    }
}