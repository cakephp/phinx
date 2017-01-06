<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Phinx\Db\Adapter\SqlServerAdapter;

class SqlServerAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\SqlServerAdapter
     */
    private $adapter;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_SQLSRV_ENABLED) {
            $this->markTestSkipped('SqlServer tests disabled. See TESTS_PHINX_DB_ADAPTER_SQLSRV_ENABLED constant.');
        }

        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_SQLSRV_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_SQLSRV_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_SQLSRV_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_SQLSRV_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_SQLSRV_PORT
        );
        $this->adapter = new SqlServerAdapter($options, new ArrayInput([]), new NullOutput());

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
            'host' => TESTS_PHINX_DB_ADAPTER_SQLSRV_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_SQLSRV_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_SQLSRV_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        );

        try {
            $adapter = new SqlServerAdapter($options, new ArrayInput([]), new NullOutput());
            $adapter->connect();
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
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

    public function testSchemaTableIsCreatedWithPrimaryKey()
    {
        $this->adapter->connect();
        $table = new \Phinx\Db\Table($this->adapter->getSchemaTableName(), array(), $this->adapter);
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), array('version')));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('[test_table]', $this->adapter->quoteTableName('test_table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('[test_column]', $this->adapter->quoteColumnName('test_column'));
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

    public function testCreateTableCustomIdColumn()
    {
        $table = new \Phinx\Db\Table('ntable', array('id' => 'custom_id'), $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = array(
            'id' => false
        );
        $table = new \Phinx\Db\Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = array(
            'id' => false,
            'primary_key' => array('user_id', 'tag_id')
        );
        $table = new \Phinx\Db\Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->addColumn('tag_id', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('user_id', 'tag_id')));
        $this->assertTrue($this->adapter->hasIndex('table1', array('tag_id', 'USER_ID')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('tag_id', 'user_email')));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('name', 'string')
              ->addIndex('email')
              ->addIndex('name')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('email')));
        $this->assertTrue($this->adapter->hasIndex('table1', array('name')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_email')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_name')));
    }

    public function testCreateTableWithUniqueIndexes()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', array('unique' => true))
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('email')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_email')));
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', array('name' => 'myemailindex'))
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('email')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('email', 'user_email')));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
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
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_zero') {
                $this->assertEquals("test", $column->getDefault());
            }
        }
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', array('default' => 0))
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_zero') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('0', $column->getDefault());
            }
        }
    }

    public function testAddColumnWithDefaultNull() {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_null', 'string', array('null' => true, 'default' => null))
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_null') {
                $this->assertNull($column->getDefault());
            }
        }
    }

    public function testAddColumnWithDefaultBool() {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table
            ->addColumn('default_false', 'integer', array('default' => false))
            ->addColumn('default_true', 'integer', array('default' => true))
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_false') {
                $this->assertSame(0, $column->getDefault());
            }
            if ($column->getName() == 'default_true') {
                $this->assertSame(1, $column->getDefault());
            }
        }
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
            $this->assertInstanceOf(
                 'InvalidArgumentException',
                     $e,
                     'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertEquals('The specified column does not exist: column2', $e->getMessage());
        }
    }

    public function testChangeColumn()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn2 = new \Phinx\Db\Table\Column();
        $newColumn2->setName('column2')
                   ->setType('string')
                   ->setNull(true);
        $table->changeColumn('column1', $newColumn2);
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() == 'column2') {
                $this->assertTrue($column->isNull());
            }
        }
    }

    public function testChangeColumnDefaults()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string', array('default' => 'test'))
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $columns = $this->adapter->getColumns('t');
        $this->assertSame('test', $columns['column1']->getDefault());

        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault('another test');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $columns = $this->adapter->getColumns('t');
        $this->assertSame('another test', $columns['column1']->getDefault());
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string', array('null' => true, 'default' => 'test'))
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault(null);
        $table->changeColumn('column1', $newColumn1);
        $columns = $this->adapter->getColumns('t');
        $this->assertNull($columns['column1']->getDefault());
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault(0);
        $table->changeColumn('column1', $newColumn1);
        $columns = $this->adapter->getColumns('t');
        $this->assertSame(0, $columns['column1']->getDefault());
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
        $table->addColumn('column1', 'string', array('null' => true, 'default' => null))
              ->addColumn('column2', 'integer', array('default' => 0))
              ->addColumn('column3', 'biginteger', array('default' => 5))
              ->addColumn('column4', 'text', array('default' => 'text'))
              ->addColumn('column5', 'float')
              ->addColumn('column6', 'decimal')
              ->addColumn('column7', 'time')
              ->addColumn('column8', 'timestamp')
              ->addColumn('column9', 'date')
              ->addColumn('column10', 'boolean')
              ->addColumn('column11', 'datetime')
              ->addColumn('column12', 'binary')
              ->addColumn('column13', 'string', array('limit' => 10));
        $pendingColumns = $table->getPendingColumns();
        $table->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertCount(count($pendingColumns) + 1, $columns);
        for ($i = 0; $i++; $i < count($pendingColumns)) {
            $this->assertEquals($pendingColumns[$i], $columns[$i + 1]);
        }

        $this->assertNull($columns['column1']->getDefault());
        $this->assertSame(0, $columns['column2']->getDefault());
        $this->assertSame(5, $columns['column3']->getDefault());
        $this->assertSame('text', $columns['column4']->getDefault());
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

    public function testGetIndexes()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->addIndex('email')
              ->addIndex(array('email', 'username'), array('unique' => true, 'name' => 'email_username'))
              ->save();

        $indexes = $this->adapter->getIndexes('table1');
        $this->assertArrayHasKey('PK_table1', $indexes);
        $this->assertArrayHasKey('table1_email', $indexes);
        $this->assertArrayHasKey('email_username', $indexes);

        $this->assertEquals(array('id'), $indexes['PK_table1']['columns']);
        $this->assertEquals(array('email'), $indexes['table1_email']['columns']);
        $this->assertEquals(array('email', 'username'), $indexes['email_username']['columns']);
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
        return;
        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', array(), $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(array('fname', 'lname'))
               ->save();
        $this->assertTrue($table2->hasIndex(array('fname', 'lname')));
        $this->adapter->dropIndex($table2->getName(), array('fname', 'lname'));
        $this->assertFalse($table2->hasIndex(array('fname', 'lname')));

        // index with name specified, but dropping it by column name
        $table3 = new \Phinx\Db\Table('table3', array(), $this->adapter);
        $table3->addColumn('email', 'string')
               ->addIndex('email', array('name' => 'someindexname'))
               ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('table4', array(), $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(array('fname', 'lname'), array('name' => 'multiname'))
               ->save();
        $this->assertTrue($table4->hasIndex(array('fname', 'lname')));
        $this->adapter->dropIndex($table4->getName(), array('fname', 'lname'));
        $this->assertFalse($table4->hasIndex(array('fname', 'lname')));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', array('name' => 'myemailindex'))
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', array(), $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(array('fname', 'lname'),
                   array('name' => 'twocolumnuniqueindex', 'unique' => true))
               ->save();
        $this->assertTrue($table2->hasIndex(array('fname', 'lname')));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnuniqueindex');
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
           ->setReferencedColumns(array('id'));

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
           ->setReferencedColumns(array('id'));

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
        $this->adapter->dropForeignKey($table->getName(), array('ref_table_id'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), array('ref_table_id')));
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_SQLSRV_DATABASE));
    }

    public function testDropDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->createDatabase('phinx_temp_database');
        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->dropDatabase('phinx_temp_database');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The type: "idontexist" is not supported
     */
    public function testInvalidSqlType()
    {
        $this->adapter->getSqlType('idontexist');
    }

    public function testGetPhinxType()
    {
        $this->assertEquals('integer', $this->adapter->getPhinxType('int'));
        $this->assertEquals('integer', $this->adapter->getPhinxType('integer'));

        $this->assertEquals('biginteger', $this->adapter->getPhinxType('bigint'));

        $this->assertEquals('decimal', $this->adapter->getPhinxType('decimal'));
        $this->assertEquals('decimal', $this->adapter->getPhinxType('numeric'));

        $this->assertEquals('float', $this->adapter->getPhinxType('real'));

        $this->assertEquals('boolean', $this->adapter->getPhinxType('bit'));

        $this->assertEquals('string', $this->adapter->getPhinxType('varchar'));
        $this->assertEquals('string', $this->adapter->getPhinxType('nvarchar'));
        $this->assertEquals('char', $this->adapter->getPhinxType('char'));
        $this->assertEquals('char', $this->adapter->getPhinxType('nchar'));

        $this->assertEquals('text', $this->adapter->getPhinxType('text'));

        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamp'));

        $this->assertEquals('date', $this->adapter->getPhinxType('date'));

        $this->assertEquals('datetime', $this->adapter->getPhinxType('datetime'));

    }

    public function testAddColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('field1', 'string', array('comment' => $comment = 'Comments from column "field1"'))
              ->save();

        $resultComment = $this->adapter->getColumnComment('table1', 'field1');

        $this->assertEquals($comment, $resultComment, 'Dont set column comment correctly');
    }

    /**
     * @dependss testAddColumnComment
     */
    public function testChangeColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('field1', 'string', array('comment' => 'Comments from column "field1"'))
              ->save();

        $table->changeColumn('field1', 'string', array('comment' => $comment = 'New Comments from column "field1"'))
              ->save();

        $resultComment = $this->adapter->getColumnComment('table1', 'field1');

        $this->assertEquals($comment, $resultComment, 'Dont change column comment correctly');
    }

    /**
     * @depends testAddColumnComment
     */
    public function testRemoveColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('field1', 'string', array('comment' => 'Comments from column "field1"'))
              ->save();

        $table->changeColumn('field1', 'string', array('comment' => 'null'))
              ->save();

        $resultComment = $this->adapter->getColumnComment('table1', 'field1');

        $this->assertEmpty($resultComment, 'Dont remove column comment correctly');
    }

    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testForignKeysArePropertlyEscaped()
    {
        $userId = 'user';
        $sessionId = 'session';

        $local = new \Phinx\Db\Table('users', array('primary_key' => $userId, 'id' => $userId), $this->adapter);
        $local->create();

        $foreign = new \Phinx\Db\Table('sessions', array('primary_key' => $sessionId, 'id' => $sessionId), $this->adapter);
        $foreign->addColumn('user', 'integer')
                ->addForeignKey('user', 'users', $userId)
                ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));
    }

    public function testInsertData()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert(array(
                  array(
                      'column1' => 'value1',
                      'column2' => 1,
                  ),
                  array(
                      'column1' => 'value2',
                      'column2' => 2,
                  )
              ))
              ->insert(
                  array(
                      'column1' => 'value3',
                      'column2' => 3,
                  )
              )
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
    }
}
