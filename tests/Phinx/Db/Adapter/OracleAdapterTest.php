<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\OracleAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class OracleAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\OracleAdapter
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
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
    }

    public function testSchemaTableIsCreatedWithPrimaryKey()
    {
        $this->adapter->connect();
        $table = new \Phinx\Db\Table($this->adapter->getSchemaTableName(), [], $this->adapter);
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), ['VERSION']));
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
            'primary_key' => ['USER_ID', 'TAG_ID']
        ];
        $table = new \Phinx\Db\Table('TABLE1', $options, $this->adapter);
        $table->addColumn('USER_ID', 'integer')
            ->addColumn('TAG_ID', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['USER_ID', 'TAG_ID']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['TAG_ID', 'USER_EMAIL']));

        $this->adapter->dropTable('TABLE1');
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addColumn('NAME', 'string')
            ->addIndex('EMAIL')
            ->addIndex('NAME')
            ->save();

        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['EMAIL']));
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['NAME']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['EMAIL', 'USER_EMAIL']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['email', 'USER_NAME']));

        $this->adapter->dropTable('TABLE1');
    }

    public function testCreateTableWithUniqueIndexes()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL', ['unique' => true])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['EMAIL']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['EMAIL', 'USER_EMAIL']));

        $this->adapter->dropTable('TABLE1');
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL', ['name' => 'MYEMAILINDEX'])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('TABLE1', ['EMAIL']));
        $this->assertFalse($this->adapter->hasIndex('TABLE1', ['EMAIL', 'USER_EMAIL']));
        $this->assertTrue($this->adapter->hasIndexByName('TABLE1', strtoupper('MYEMAILINDEX')));

        $this->adapter->dropTable('TABLE1');
    }

    public function testTableWithoutIndexesByName()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->save();
        $this->assertFalse($this->adapter->hasIndexByName('TABLE1', strtoupper('MYEMAILINDEX')));

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
                $this->assertNull($column->getDefault());
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
                $this->assertSame(0, (int)trim($column->getDefault()));
            }
            if ($column->getName() == 'default_true') {
                $this->assertSame(1, (int)trim($column->getDefault()));
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

        $this->assertNull($columns['column1']->getDefault());
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault(0);
        $table->changeColumn('column1', $newColumn1);
        $columns = $this->adapter->getColumns('T');
        $this->adapter->dropTable('T');
        $this->assertSame(0, (int)$columns['column1']->getDefault());
    }

    public function testDropColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $this->adapter->dropColumn('T', 'column1');
        $this->adapter->dropTable('T');
        $this->assertFalse($this->adapter->hasColumn('T', 'column1'));
    }

    public function testGetColumns()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string', ['null' => true, 'default' => null])
            ->addColumn('column2', 'integer', ['default' => 0])
            ->addColumn('column3', 'biginteger', ['default' => 5])
            ->addColumn('column4', 'text', ['default' => 'text'])
            ->addColumn('column5', 'float')
            ->addColumn('column6', 'decimal')
            ->addColumn('column8', 'timestamp')
            ->addColumn('column9', 'date')
            ->addColumn('column10', 'boolean')
            ->addColumn('column11', 'datetime')
            ->addColumn('column12', 'binary')
            ->addColumn('column13', 'string', ['limit' => 10]);
        $pendingColumns = $table->getPendingColumns();
        $table->save();
        $columns = $this->adapter->getColumns('T');

        $this->adapter->dropTable('T');

        $this->assertCount(count($pendingColumns) + 1, $columns);
        for ($i = 0; $i++; $i < count($pendingColumns)) {
            $this->assertEquals($pendingColumns[$i], $columns[$i + 1]);
        }

        $this->assertNull($columns['column1']->getDefault());
        $this->assertSame(0, (int)$columns['column2']->getDefault());
        $this->assertSame(5, (int)$columns['column3']->getDefault());
        $this->assertSame("'text'", $columns['column4']->getDefault());
    }

    public function testAddIndex()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('EMAIL'));
        $table->addIndex('EMAIL')
            ->save();

        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->adapter->dropTable('TABLE1');
    }

    public function testGetIndexes()
    {
        // single column index
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addColumn('USERNAME', 'string')
            ->addIndex('EMAIL')
            ->addIndex(['EMAIL', 'USERNAME'], ['unique' => true, 'name' => 'EMAIL_USERNAME'])
            ->save();

        $indexes = $this->adapter->getIndexes('TABLE1');

        $this->adapter->dropTable('TABLE1');

        $this->assertArrayHasKey('PK_TABLE1', $indexes);
        $this->assertArrayHasKey('TABLE1_EMAIL', $indexes);
        $this->assertArrayHasKey('EMAIL_USERNAME', $indexes);

        $this->assertEquals(['ID'], $indexes['PK_TABLE1']['columns']);
        $this->assertEquals(['EMAIL'], $indexes['TABLE1_EMAIL']['columns']);
        $this->assertEquals(['EMAIL', 'USERNAME'], $indexes['EMAIL_USERNAME']['columns']);
    }

    public function testDropIndex()
    {
        // single column index
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL')
            ->save();
        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->adapter->dropIndex($table->getName(), 'EMAIL');
        $this->assertFalse($table->hasIndex('EMAIL'));

        // multiple column index
        $TABLE2 = new \Phinx\Db\Table('TABLE2', [], $this->adapter);
        $TABLE2->addColumn('FNAME', 'string')
            ->addColumn('LNAME', 'string')
            ->addIndex(['FNAME', 'LNAME'])
            ->save();
        $this->assertTrue($TABLE2->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropIndex($TABLE2->getName(), ['FNAME', 'LNAME']);
        $this->assertFalse($TABLE2->hasIndex(['FNAME', 'LNAME']));

        // index with name specified, but dropping it by column name
        $table3 = new \Phinx\Db\Table('TABLE3', [], $this->adapter);
        $table3->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL', ['name' => 'SOMEINDEXNAME'])
            ->save();
        $this->assertTrue($table3->hasIndex('EMAIL'));
        $this->adapter->dropIndex($table3->getName(), 'EMAIL');
        $this->assertFalse($table3->hasIndex('EMAIL'));

        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('TABLE4', [], $this->adapter);
        $table4->addColumn('FNAME', 'string')
            ->addColumn('LNAME', 'string')
            ->addIndex(['FNAME', 'LNAME'], ['name' => 'multiname'])
            ->save();
        $this->assertTrue($table4->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropIndex($table4->getName(), ['FNAME', 'LNAME']);
        $this->assertFalse($table4->hasIndex(['FNAME', 'LNAME']));

        $this->adapter->dropTable('TABLE1');
        $this->adapter->dropTable('TABLE2');
        $this->adapter->dropTable('TABLE3');
        $this->adapter->dropTable('TABLE4');
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL', ['name' => 'MYEMAILINDEX'])
            ->save();
        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->adapter->dropIndexByName($table->getName(), 'MYEMAILINDEX');
        $this->assertFalse($table->hasIndex('EMAIL'));

        // multiple column index
        $TABLE2 = new \Phinx\Db\Table('TABLE2', [], $this->adapter);
        $TABLE2->addColumn('FNAME', 'string')
            ->addColumn('LNAME', 'string')
            ->addIndex(
                ['FNAME', 'LNAME'],
                ['name' => 'TWOCOLUMNINIQUEINDEX', 'unique' => true]
            )
            ->save();
        $this->assertTrue($TABLE2->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropIndexByName($TABLE2->getName(), 'TWOCOLUMNINIQUEINDEX');
        $this->assertFalse($TABLE2->hasIndex(['FNAME', 'LNAME']));

        $this->adapter->dropTable('TABLE1');
        $this->adapter->dropTable('TABLE2');
    }

    public function testAddForeignKey()
    {
        $refTable = new \Phinx\Db\Table('TEF_TABLE', [], $this->adapter);
        $refTable->addColumn('FIELD1', 'string')->save();

        $table = new \Phinx\Db\Table('TABLE', [], $this->adapter);
        $table->addColumn('TEF_TABLE_ID', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
            ->setColumns(['TEF_TABLE_ID'])
            ->setReferencedColumns(['id'])
            ->setConstraint('fk1');

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['TEF_TABLE_ID'], 'fk1'));

        $this->adapter->dropTable('TABLE');
        $this->adapter->dropTable('TEF_TABLE');
    }

    public function testDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('TEF_TABLE', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('TABLE', [], $this->adapter);
        $table->addColumn('TEF_TABLE_ID', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
            ->setColumns(['TEF_TABLE_ID'])
            ->setReferencedColumns(['id']);

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['TEF_TABLE_ID']));
        $this->adapter->dropForeignKey($table->getName(), ['TEF_TABLE_ID']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['TEF_TABLE_ID']));

        $this->adapter->dropTable('TABLE');
        $this->adapter->dropTable('TEF_TABLE');
    }

    public function testStringDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('TEF_TABLE', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('TABLE', [], $this->adapter);
        $table->addColumn('TEF_TABLE_ID', 'integer')->save();

        $fk = new \Phinx\Db\Table\ForeignKey();
        $fk->setReferencedTable($refTable)
            ->setColumns(['TEF_TABLE_ID'])
            ->setReferencedColumns(['id']);

        $this->adapter->addForeignKey($table, $fk);
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['TEF_TABLE_ID']));
        $this->adapter->dropForeignKey($table->getName(), 'TEF_TABLE_ID');
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['TEF_TABLE_ID']));

        $this->adapter->dropTable('TABLE');
        $this->adapter->dropTable('TEF_TABLE');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The type: "idontexist" is not supported
     */
    public function testInvalidSqlType()
    {
        $this->adapter->getSqlType('idontexist');
    }

    public function testGetSqlType()
    {
        $this->assertEquals(['name' => 'CHAR', 'limit' => 255], $this->adapter->getSqlType('char'));
        $this->assertEquals(['name' => 'time'], $this->adapter->getSqlType('time'));
        $this->assertEquals(['name' => 'BLOB'], $this->adapter->getSqlType('blob'));
        $this->assertEquals(['name' => 'CLOB'], $this->adapter->getSqlType('CLOB'));
        $this->assertEquals(
            [
                'name' => 'RAW',
                'precision' => 16,
                'default' => 'SYS_GUID()',
                'limit' => 2000
            ],
            $this->adapter->getSqlType('uuid')
        );
        $this->assertEquals(['name' => 'geography'], $this->adapter->getSqlType('polygon'));
        $this->assertEquals(['name' => 'varbinary', 'limit' => 'max'], $this->adapter->getSqlType('filestream'));
    }

    public function testGetPhinxType()
    {
        $this->assertEquals('integer', $this->adapter->getPhinxType('NUMBER', 10));

        $this->assertEquals('biginteger', $this->adapter->getPhinxType('NUMBER', 19));

        $this->assertEquals('decimal', $this->adapter->getPhinxType('NUMBER'));

        $this->assertEquals('float', $this->adapter->getPhinxType('FLOAT'));

        $this->assertEquals('boolean', $this->adapter->getPhinxType('NUMBER', 1));

        $this->assertEquals('string', $this->adapter->getPhinxType('VARCHAR2'));

        $this->assertEquals('char', $this->adapter->getPhinxType('CHAR'));

        $this->assertEquals('text', $this->adapter->getPhinxType('LONG'));

        $this->assertEquals('timestamp', $this->adapter->getPhinxType('TIMESTAMP(6)'));

        $this->assertEquals('date', $this->adapter->getPhinxType('DATE'));

        $this->assertEquals('blob', $this->adapter->getPhinxType('BLOB'));

        $this->assertEquals('CLOB', $this->adapter->getPhinxType('CLOB'));
    }

    public function testAddColumnComment()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => $comment = 'Comments from column "field1"'])
            ->save();

        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');

        $this->adapter->dropTable('TABLE1');
        $this->assertEquals($comment, $resultComment, 'Dont set column comment correctly');
    }

    public function testGetColumnCommentEmptyReturn()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'NULL'])
            ->save();

        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');

        $this->adapter->dropTable('TABLE1');
        $this->assertEquals('', $resultComment, 'NULL');
    }

    /**
     * @dependss testAddColumnComment
     */
    public function testChangeColumnComment()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
            ->save();

        $table->changeColumn('field1', 'string', ['comment' => $comment = 'New Comments from column "field1"'])
            ->save();

        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');
        $this->adapter->dropTable('TABLE1');
        $this->assertEquals($comment, $resultComment, 'Dont change column comment correctly');
    }

    /**
     * @depends testAddColumnComment
     */
    public function testRemoveColumnComment()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
            ->save();

        $table->changeColumn('field1', 'string', ['comment' => 'null'])
            ->save();

        $resultComment = $this->adapter->getColumnComment('TABLE1', 'field1');
        $this->adapter->dropTable('TABLE1');

        $this->assertEmpty($resultComment, 'Dont remove column comment correctly');
    }

    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testForignKeysArePropertlyEscaped()
    {
        $userId = 'USER123';
        $sessionId = 'SESSION123';

        $local = new \Phinx\Db\Table('USERS', ['primary_key' => $userId, 'id' => $userId], $this->adapter);
        $local->create();

        $foreign = new \Phinx\Db\Table(
            'SESSIONS123',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('USER123', 'integer')
            ->addForeignKey('USER123', 'USERS', $userId, ['constraint' => 'USER_SESSION_ID'])
            ->create();

        $this->assertTrue($foreign->hasForeignKey('USER123'));

        $this->adapter->dropTable('SESSIONS123');
        $this->adapter->dropTable('USERS');
    }

    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testDontHasForeignKey()
    {
        $userId = 'USER123';
        $sessionId = 'SESSION123';

        $local = new \Phinx\Db\Table('USERS', ['primary_key' => $userId, 'id' => $userId], $this->adapter);
        $local->create();

        $foreign = new \Phinx\Db\Table(
            'SESSIONS123',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('USER123', 'integer')
            ->addForeignKey('USER123', 'USERS', $userId, ['constraint' => 'USER_SESSION_ID'])
            ->create();

        $this->assertFalse($foreign->hasForeignKey('USER123', 'a'));

        $this->adapter->dropTable('SESSIONS123');
        $this->adapter->dropTable('USERS');
    }

    public function testBulkInsertData()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->insert([
                [
                    'column1' => 'value1',
                    'column2' => 1,
                ],
                [
                    'column1' => 'value2',
                    'column2' => 2,
                ]
            ])
            ->insert(
                [
                    'column1' => 'value3',
                    'column2' => 3,
                ]
            );
        $this->adapter->createTable($table);
        $this->adapter->bulkinsert($table, $table->getData());
        $table->reset();

        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);

        $this->adapter->dropTable('TABLE1');
    }

    public function testInsertData()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->insert([
                [
                    'column1' => 'value1',
                    'column2' => 1,
                ],
                [
                    'column1' => 'value2',
                    'column2' => 2,
                ]
            ])
            ->insert(
                [
                    'column1' => 'value3',
                    'column2' => 3,
                ]
            )
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);

        $this->adapter->dropTable('TABLE1');
    }

    public function testTruncateTable()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->insert([
                [
                    'column1' => 'value1',
                    'column2' => 1,
                ],
                [
                    'column1' => 'value2',
                    'column2' => 2,
                ]
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');
        $this->assertCount(2, $rows);
        $table->truncate();
        $rows = $this->adapter->fetchAll('SELECT * FROM TABLE1');
        $this->assertCount(0, $rows);

        $this->adapter->dropTable('TABLE1');
    }
}
