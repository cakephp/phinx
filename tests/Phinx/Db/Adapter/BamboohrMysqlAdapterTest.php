<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Output\NullOutput;
use Phinx\Db\Adapter\BambooHRMysqlAdapter;

class BamboohrMysqlAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\MysqlAdapter
     */
    private $adapter;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED) {
            $this->markTestSkipped('Mysql tests disabled. See TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED constant.');
        }

        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT
        );
        $this->adapter = new BambooHRMysqlAdapter($options, new NullOutput());

        // ensure the database is empty for each test
        $this->adapter->dropDatabase($options['name']);
        $this->adapter->createDatabase($options['name']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    public function tearDown()
    {
        unset($this->adapter);
    }
    
    public function testCreateTable()
    {
        $table = new \Phinx\Db\Table('ntable', array(), $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }
    
    public function testHasTable()
    {
        $table = new \Phinx\Db\Table('ntable', array(), $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        
    }
    /**
     * @expectedException \Exception
     * @expectedExceptionMessage uh uh - we don't support describe table
     */
    public function testDescribeTable()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string');
        $table->save();

        $described = $this->adapter->describeTable('t');
        
    }
    
    /**
     * @expectedException \Exception
     */
    public function testDropForeignKeyNoConstraint()
    {
        $refTable = new \Phinx\Db\Table('ref_table', array(), $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', array(), $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(array('ref_table_id'))
           ->setReferencedColumns(array('id'));

        $this->adapter->addForeignKey($table, $fk);
	// this should throw an exception if used without a constraint
        $this->adapter->dropForeignKey($table->getName(), array('ref_table_id'));
        
    }
    
    /**
     * use this test since we made changes to getForeignKeys which is referenced in hasForeignKey
     */
    public function testHasForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', array(), $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', array(), $this->adapter);
        $table->addColumn('ref_table_id', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
           ->setColumns(array('ref_table_id'))
           ->setReferencedColumns(array('id'));

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), array('ref_table_id2')));
    }


    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE));
    }

   
}
