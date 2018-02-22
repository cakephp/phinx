<?php
/**
 * User: t-superofelipe
 * Date: 19/02/18
 * Time: 11:15
 */

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\OracleAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class OracleAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\SqlServerAdapter
     */
    private $adapter;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_ORACLE_ENABLED) {
            $this->markTestSkipped('Oracle tests disabled. See TESTS_PHINX_DB_ADAPTER_ORACLE_ENABLED constant.');
        }

        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_ORACLE_HOST,
            'user' => TESTS_PHINX_DB_ADAPTER_ORACLE_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_ORACLE_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_ORACLE_PORT,
            'sid' => TESTS_PHINX_DB_ADAPTER_ORACLE_SID
        ];

        $this->adapter = new OracleAdapter($options, new ArrayInput([]), new NullOutput());
        $this->adapter->getConnection();

        // ensure the database is empty for each test
//        $this->adapter->dropDatabase($options['connectionString']);
//        $this->adapter->createDatabase($options['connectionString']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    public function tearDown()
    {
        unset($this->adapter);
    }

    public function testConnection()
    {
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
    }

    public function testConnectionWithoutPort()
    {
        $options = $this->adapter->getOptions();
        unset($options['port']);
        $this->adapter->setOptions($options);
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
    }

    public function testConnectionWithInvalidCredentials()
    {
        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_ORACLE_HOST,
            'user' => 'invaliduser',
            'pass' => 'invalidpass',
            'port' => TESTS_PHINX_DB_ADAPTER_ORACLE_PORT,
            'sid' => TESTS_PHINX_DB_ADAPTER_ORACLE_SID
        ];

        try {
            $adapter = new OracleAdapter($options, new ArrayInput([]), new NullOutput());
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
        $table = new \Phinx\Db\Table($this->adapter->getSchemaTableName(), [], $this->adapter);
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), ['version']));
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
        $table = new \Phinx\Db\Table('NTABLE', [], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('NTABLE'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'id'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'email'));
        $this->assertFalse($this->adapter->hasColumn('NTABLE', 'address'));
        $this->adapter->dropTable('NTABLE');
    }

    public function testCreateTableCustomIdColumn()
    {
        $table = new \Phinx\Db\Table('NTABLE', ['id' => 'custom_id'], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('NTABLE'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'email'));
        $this->assertFalse($this->adapter->hasColumn('NTABLE', 'address'));
        $this->adapter->dropTable('NTABLE');
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = [
            'id' => false
        ];
        $table = new \Phinx\Db\Table('ATABLE', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->save();
        $this->assertFalse($this->adapter->hasColumn('ATABLE', 'id'));
        $this->adapter->dropTable('ATABLE');
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id']
        ];
        $table = new \Phinx\Db\Table('TABLE1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['user_id', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['tag_id', 'user_email']));

        $this->adapter->dropTable('TABLE1');
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addColumn('name', 'string')
            ->addIndex('email')
            ->addIndex('name')
            ->save();

        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['email']));
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['name']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['email', 'user_email']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['email', 'user_name']));

        $this->adapter->dropTable('TABLE1');
    }

    public function testCreateTableWithUniqueIndexes()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['unique' => true])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['email', 'user_email']));

        $this->adapter->dropTable('TABLE1');
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailindex'])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('TABLE1', strtoupper('myemailindex')));

        $this->adapter->dropTable('TABLE1');
    }

    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('TABLE1'));
        $this->assertFalse($this->adapter->hasTable('TABLE2'));
        $this->adapter->renameTable('TABLE1', 'TABLE2');
        $this->assertFalse($this->adapter->hasTable('TABLE1'));
        $this->assertTrue($this->adapter->hasTable('TABLE2'));

        $this->adapter->dropTable('TABLE2');
    }

    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
            ->save();
        $this->assertTrue($table->hasColumn('email'));
        $this->adapter->dropTable('TABLE1');

    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_zero') {
                $this->assertEquals("'test'", trim($column->getDefault()));
            }
        }

        $this->adapter->dropTable('TABLE1');
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_zero') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('0', trim($column->getDefault()));
            }
        }
        $this->adapter->dropTable('TABLE1');

    }

    public function testAddColumnWithDefaultNull()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_null', 'string', ['null' => true, 'default' => null])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_null') {
                $null_ = (trim($column->getDefault()) == 'NULL' ? null : (trim($column->getDefault())));
                $this->assertNull($null_);
            }
        }

        $this->adapter->dropTable('TABLE1');

    }

    public function testAddColumnWithDefaultBool()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table
            ->addColumn('default_false', 'integer', ['default' => false])
            ->addColumn('default_true', 'integer', ['default' => true])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_false') {
                $this->assertSame(0, (int) trim($column->getDefault()));
            }
            if ($column->getName() == 'default_true') {
                $this->assertSame(1, (int) trim($column->getDefault()));
            }
        }

        $this->adapter->dropTable('TABLE1');

    }

    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('T', 'column2'));
        $this->adapter->renameColumn('T', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('T', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('T', 'column2'));

        $this->adapter->dropTable('T');

    }

    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();

        try {
            $this->adapter->renameColumn('T', 'column2', 'column1');
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertEquals('The specified column does not exist: column2', $e->getMessage());
        }

        $this->adapter->dropTable('T');

    }

    public function testChangeColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();

        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $newColumn2 = new \Phinx\Db\Table\Column();
        $newColumn2->setName('column2')
            ->setType('string')
            ->setNull(true);
        $table->changeColumn('column1', $newColumn2);
        $this->assertFalse($this->adapter->hasColumn('T', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('T', 'column2'));
        $columns = $this->adapter->getColumns('T');
        foreach ($columns as $column) {
            if ($column->getName() == 'column2') {
                $this->assertTrue($column->isNull());
            }
        }

        $this->adapter->dropTable('T');
    }

    public function testChangeColumnDefaults()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));

        $columns = $this->adapter->getColumns('T');

        $this->assertSame("'test'", trim($columns['column1']->getDefault()));

        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault('another test');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));

        $columns = $this->adapter->getColumns('T');
        $this->assertSame("'another test'", trim($columns['column1']->getDefault()));

        $this->adapter->dropTable('T');

    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string', ['null' => true, 'default' => 'test'])
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault(null);
        $table->changeColumn('column1', $newColumn1);
        $columns = $this->adapter->getColumns('T');

        $this->adapter->dropTable('T');
        var_dump($columns['column1']);
        $this->assertNull($columns['column1']->getDefault());


    }

//    public function testChangeColumnDefaultToZero()
//    {
//        $table = new \Phinx\Db\Table('t', [], $this->adapter);
//        $table->addColumn('column1', 'integer')
//            ->save();
//        $newColumn1 = new \Phinx\Db\Table\Column();
//        $newColumn1
//            ->setType('string')
//            ->setDefault(0);
//        $table->changeColumn('column1', $newColumn1);
//        $columns = $this->adapter->getColumns('t');
//        $this->assertSame(0, $columns['column1']->getDefault());
//    }
//
//    public function testDropColumn()
//    {
//        $table = new \Phinx\Db\Table('t', [], $this->adapter);
//        $table->addColumn('column1', 'string')
//            ->save();
//        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
//        $this->adapter->dropColumn('t', 'column1');
//        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
//    }
//
//    public function testGetColumns()
//    {
//        $table = new \Phinx\Db\Table('t', [], $this->adapter);
//        $table->addColumn('column1', 'string', ['null' => true, 'default' => null])
//            ->addColumn('column2', 'integer', ['default' => 0])
//            ->addColumn('column3', 'biginteger', ['default' => 5])
//            ->addColumn('column4', 'text', ['default' => 'text'])
//            ->addColumn('column5', 'float')
//            ->addColumn('column6', 'decimal')
//            ->addColumn('column7', 'time')
//            ->addColumn('column8', 'timestamp')
//            ->addColumn('column9', 'date')
//            ->addColumn('column10', 'boolean')
//            ->addColumn('column11', 'datetime')
//            ->addColumn('column12', 'binary')
//            ->addColumn('column13', 'string', ['limit' => 10]);
//        $pendingColumns = $table->getPendingColumns();
//        $table->save();
//        $columns = $this->adapter->getColumns('t');
//        $this->assertCount(count($pendingColumns) + 1, $columns);
//        for ($i = 0; $i++; $i < count($pendingColumns)) {
//            $this->assertEquals($pendingColumns[$i], $columns[$i + 1]);
//        }
//
//        $this->assertNull($columns['column1']->getDefault());
//        $this->assertSame(0, $columns['column2']->getDefault());
//        $this->assertSame(5, $columns['column3']->getDefault());
//        $this->assertSame('text', $columns['column4']->getDefault());
//    }
//
//    public function testAddIndex()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('email', 'string')
//            ->save();
//        $this->assertFalse($table->hasIndex('email'));
//        $table->addIndex('email')
//            ->save();
//        $this->assertTrue($table->hasIndex('email'));
//    }
//
//    public function testGetIndexes()
//    {
//        // single column index
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('email', 'string')
//            ->addColumn('username', 'string')
//            ->addIndex('email')
//            ->addIndex(['email', 'username'], ['unique' => true, 'name' => 'email_username'])
//            ->save();
//
//        $indexes = $this->adapter->getIndexes('TABLE1');
//        $this->assertArrayHasKey('PK_TABLE1', $indexes);
//        $this->assertArrayHasKey('TABLE1_email', $indexes);
//        $this->assertArrayHasKey('email_username', $indexes);
//
//        $this->assertEquals(['id'], $indexes['PK_TABLE1']['columns']);
//        $this->assertEquals(['email'], $indexes['TABLE1_email']['columns']);
//        $this->assertEquals(['email', 'username'], $indexes['email_username']['columns']);
//    }
//
//    public function testDropIndex()
//    {
//        // single column index
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('email', 'string')
//            ->addIndex('email')
//            ->save();
//        $this->assertTrue($table->hasIndex('email'));
//        $this->adapter->dropIndex($table->getName(), 'email');
//        $this->assertFalse($table->hasIndex('email'));
//
//        // multiple column index
//        $TABLE2 = new \Phinx\Db\Table('TABLE2', [], $this->adapter);
//        $TABLE2->addColumn('fname', 'string')
//            ->addColumn('lname', 'string')
//            ->addIndex(['fname', 'lname'])
//            ->save();
//        $this->assertTrue($TABLE2->hasIndex(['fname', 'lname']));
//        $this->adapter->dropIndex($TABLE2->getName(), ['fname', 'lname']);
//        $this->assertFalse($TABLE2->hasIndex(['fname', 'lname']));
//
//        // index with name specified, but dropping it by column name
//        $table3 = new \Phinx\Db\Table('table3', [], $this->adapter);
//        $table3->addColumn('email', 'string')
//            ->addIndex('email', ['name' => 'someindexname'])
//            ->save();
//        $this->assertTrue($table3->hasIndex('email'));
//        $this->adapter->dropIndex($table3->getName(), 'email');
//        $this->assertFalse($table3->hasIndex('email'));
//
//        // multiple column index with name specified
//        $table4 = new \Phinx\Db\Table('table4', [], $this->adapter);
//        $table4->addColumn('fname', 'string')
//            ->addColumn('lname', 'string')
//            ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
//            ->save();
//        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
//        $this->adapter->dropIndex($table4->getName(), ['fname', 'lname']);
//        $this->assertFalse($table4->hasIndex(['fname', 'lname']));
//    }
//
//    public function testDropIndexByName()
//    {
//        // single column index
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('email', 'string')
//            ->addIndex('email', ['name' => 'myemailindex'])
//            ->save();
//        $this->assertTrue($table->hasIndex('email'));
//        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
//        $this->assertFalse($table->hasIndex('email'));
//
//        // multiple column index
//        $TABLE2 = new \Phinx\Db\Table('TABLE2', [], $this->adapter);
//        $TABLE2->addColumn('fname', 'string')
//            ->addColumn('lname', 'string')
//            ->addIndex(
//                ['fname', 'lname'],
//                ['name' => 'twocolumnuniqueindex', 'unique' => true]
//            )
//            ->save();
//        $this->assertTrue($TABLE2->hasIndex(['fname', 'lname']));
//        $this->adapter->dropIndexByName($TABLE2->getName(), 'twocolumnuniqueindex');
//        $this->assertFalse($TABLE2->hasIndex(['fname', 'lname']));
//    }
//
//    public function testAddForeignKey()
//    {
//        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
//        $refTable->addColumn('field1', 'string')->save();
//
//        $table = new \Phinx\Db\Table('table', [], $this->adapter);
//        $table->addColumn('ref_table_id', 'integer')->save();
//
//        $fk = new \Phinx\Db\Table\ForeignKey();
//        $fk->setReferencedTable($refTable)
//            ->setColumns(['ref_table_id'])
//            ->setReferencedColumns(['id'])
//            ->setConstraint('fk1');
//
//        $this->adapter->addForeignKey($table, $fk);
//        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'fk1'));
//    }
//
//    public function dropForeignKey()
//    {
//        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
//        $refTable->addColumn('field1', 'string')->save();
//
//        $table = new \Phinx\Db\Table('table', [], $this->adapter);
//        $table->addColumn('ref_table_id', 'integer')->save();
//
//        $fk = new \Phinx\Db\Table\ForeignKey();
//        $fk->setReferencedTable($refTable)
//            ->setColumns(['ref_table_id'])
//            ->setReferencedColumns(['id']);
//
//        $this->adapter->addForeignKey($table, $fk);
//        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
//        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
//        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
//    }
//
//    public function testHasDatabase()
//    {
//        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
//        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_SQLSRV_DATABASE));
//    }
//
//    public function testDropDatabase()
//    {
//        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
//        $this->adapter->createDatabase('phinx_temp_database');
//        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
//        $this->adapter->dropDatabase('phinx_temp_database');
//    }
//
//    /**
//     * @expectedException \RuntimeException
//     * @expectedExceptionMessage The type: "idontexist" is not supported
//     */
//    public function testInvalidSqlType()
//    {
//        $this->adapter->getSqlType('idontexist');
//    }
//
//    public function testGetPhinxType()
//    {
//        $this->assertEquals('integer', $this->adapter->getPhinxType('int'));
//        $this->assertEquals('integer', $this->adapter->getPhinxType('integer'));
//
//        $this->assertEquals('biginteger', $this->adapter->getPhinxType('bigint'));
//
//        $this->assertEquals('decimal', $this->adapter->getPhinxType('decimal'));
//        $this->assertEquals('decimal', $this->adapter->getPhinxType('numeric'));
//
//        $this->assertEquals('float', $this->adapter->getPhinxType('real'));
//
//        $this->assertEquals('boolean', $this->adapter->getPhinxType('bit'));
//
//        $this->assertEquals('string', $this->adapter->getPhinxType('varchar'));
//        $this->assertEquals('string', $this->adapter->getPhinxType('nvarchar'));
//        $this->assertEquals('char', $this->adapter->getPhinxType('char'));
//        $this->assertEquals('char', $this->adapter->getPhinxType('nchar'));
//
//        $this->assertEquals('text', $this->adapter->getPhinxType('text'));
//
//        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamp'));
//
//        $this->assertEquals('date', $this->adapter->getPhinxType('date'));
//
//        $this->assertEquals('datetime', $this->adapter->getPhinxType('datetime'));
//    }
//
//    public function testAddColumnComment()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('field1', 'string', ['comment' => $comment = 'Comments from column "field1"'])
//            ->save();
//
//        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');
//
//        $this->assertEquals($comment, $resultComment, 'Dont set column comment correctly');
//    }
//
//    /**
//     * @dependss testAddColumnComment
//     */
//    public function testChangeColumnComment()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
//            ->save();
//
//        $table->changeColumn('field1', 'string', ['comment' => $comment = 'New Comments from column "field1"'])
//            ->save();
//
//        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');
//
//        $this->assertEquals($comment, $resultComment, 'Dont change column comment correctly');
//    }
//
//    /**
//     * @depends testAddColumnComment
//     */
//    public function testRemoveColumnComment()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
//            ->save();
//
//        $table->changeColumn('field1', 'string', ['comment' => 'null'])
//            ->save();
//
//        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');
//
//        $this->assertEmpty($resultComment, 'Dont remove column comment correctly');
//    }
//
//    /**
//     * Test that column names are properly escaped when creating Foreign Keys
//     */
//    public function testForignKeysArePropertlyEscaped()
//    {
//        $userId = 'user';
//        $sessionId = 'session';
//
//        $local = new \Phinx\Db\Table('users', ['primary_key' => $userId, 'id' => $userId], $this->adapter);
//        $local->create();
//
//        $foreign = new \Phinx\Db\Table('sessions', ['primary_key' => $sessionId, 'id' => $sessionId], $this->adapter);
//        $foreign->addColumn('user', 'integer')
//            ->addForeignKey('user', 'users', $userId)
//            ->create();
//
//        $this->assertTrue($foreign->hasForeignKey('user'));
//    }
//
//    public function testBulkInsertData()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('column1', 'string')
//            ->addColumn('column2', 'integer')
//            ->insert([
//                [
//                    'column1' => 'value1',
//                    'column2' => 1,
//                ],
//                [
//                    'column1' => 'value2',
//                    'column2' => 2,
//                ]
//            ])
//            ->insert(
//                [
//                    'column1' => 'value3',
//                    'column2' => 3,
//                ]
//            );
//        $this->adapter->createTable($table);
//        $this->adapter->bulkinsert($table, $table->getData());
//        $table->reset();
//
//        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');
//
//        $this->assertEquals('value1', $rows[0]['column1']);
//        $this->assertEquals('value2', $rows[1]['column1']);
//        $this->assertEquals('value3', $rows[2]['column1']);
//        $this->assertEquals(1, $rows[0]['column2']);
//        $this->assertEquals(2, $rows[1]['column2']);
//        $this->assertEquals(3, $rows[2]['column2']);
//    }
//
//    public function testInsertData()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('column1', 'string')
//            ->addColumn('column2', 'integer')
//            ->insert([
//                [
//                    'column1' => 'value1',
//                    'column2' => 1,
//                ],
//                [
//                    'column1' => 'value2',
//                    'column2' => 2,
//                ]
//            ])
//            ->insert(
//                [
//                    'column1' => 'value3',
//                    'column2' => 3,
//                ]
//            )
//            ->save();
//
//        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');
//
//        $this->assertEquals('value1', $rows[0]['column1']);
//        $this->assertEquals('value2', $rows[1]['column1']);
//        $this->assertEquals('value3', $rows[2]['column1']);
//        $this->assertEquals(1, $rows[0]['column2']);
//        $this->assertEquals(2, $rows[1]['column2']);
//        $this->assertEquals(3, $rows[2]['column2']);
//    }
//
//    public function testTruncateTable()
//    {
//        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
//        $table->addColumn('column1', 'string')
//            ->addColumn('column2', 'integer')
//            ->insert([
//                [
//                    'column1' => 'value1',
//                    'column2' => 1,
//                ],
//                [
//                    'column1' => 'value2',
//                    'column2' => 2,
//                ]
//            ])
//            ->save();
//
//        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');
//        $this->assertCount(2, $rows);
//        $table->truncate();
//        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');
//        $this->assertCount(0, $rows);
//    }
}
