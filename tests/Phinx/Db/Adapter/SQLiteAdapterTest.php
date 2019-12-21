<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Table\Column;
use Phinx\Util\Literal;
use Phinx\Util\Expression;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class SQLiteAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\SQLiteAdapter
     */
    private $adapter;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED) {
            $this->markTestSkipped('SQLite tests disabled. See TESTS_PHINX_DB_ADAPTER_SQLITE_ENABLED constant.');
        }

        $options = [
            'name' => TESTS_PHINX_DB_ADAPTER_SQLITE_DATABASE,
            'suffix' => TESTS_PHINX_DB_ADAPTER_SQLITE_SUFFIX,
            'memory' => TESTS_PHINX_DB_ADAPTER_SQLITE_MEMORY,
        ];
        $this->adapter = new SQLiteAdapter($options, new ArrayInput([]), new NullOutput());

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
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $this->adapter->getConnection()->getAttribute(\PDO::ATTR_ERRMODE));
    }

    public function testConnectionWithFetchMode()
    {
        $options = $this->adapter->getOptions();
        $options['fetch_mode'] = 'assoc';
        $this->adapter->setOptions($options);
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
        $this->assertSame(\PDO::FETCH_ASSOC, $this->adapter->getConnection()->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testBeginTransaction()
    {
        $this->adapter->beginTransaction();

        $this->assertTrue(
            $this->adapter->getConnection()->inTransaction(),
            "Underlying PDO instance did not detect new transaction"
        );
    }

    public function testRollbackTransaction()
    {
        $this->adapter->getConnection()
            ->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->adapter->beginTransaction();
        $this->adapter->rollbackTransaction();

        $this->assertFalse(
            $this->adapter->getConnection()->inTransaction(),
            "Underlying PDO instance did not detect rolled back transaction"
        );
    }

    public function testCommitTransactionTransaction()
    {
        $this->adapter->getConnection()
            ->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->adapter->beginTransaction();
        $this->adapter->commitTransaction();

        $this->assertFalse(
            $this->adapter->getConnection()->inTransaction(),
            "Underlying PDO instance didn't detect committed transaction"
        );
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
        $this->assertEquals('`test_table`', $this->adapter->quoteTableName('test_table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('`test_column`', $this->adapter->quoteColumnName('test_column'));
    }

    public function testCreateTable()
    {
        $table = new \Phinx\Db\Table('ntable', [], $this->adapter);
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
        $table = new \Phinx\Db\Table('ntable', ['id' => 'custom_id'], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        //ensure the primary key is not nullable
        /** @var \Phinx\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertEquals(true, $idColumn->getIdentity());
        $this->assertEquals(false, $idColumn->isNull());
    }

    public function testCreateTableIdentityIdColumn()
    {
        $table = new \Phinx\Db\Table('ntable', ['id' => false, 'primary_key' => ['custom_id']], $this->adapter);
        $table->addColumn('custom_id', 'integer', ['identity' => true])
            ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));

        /** @var \Phinx\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertEquals(true, $idColumn->getIdentity());
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = [
            'id' => false,
        ];
        $table = new \Phinx\Db\Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new \Phinx\Db\Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'USER_ID']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addColumn('name', 'string')
            ->addIndex('email')
            ->addIndex('name')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['name']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_name']));
    }

    public function testCreateTableWithUniqueIndexes()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['unique' => true])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndexes()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailindex'])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
        $this->assertTrue($this->adapter->hasIndexByName('table1', 'myemailindex'));
    }

    public function testCreateTableWithMultiplePKsAndUniqueIndexes()
    {
        $this->markTestIncomplete();
    }

    public function testCreateTableWithForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer');
        $table->addForeignKey('ref_table_id', 'ref_table', 'id');
        $table->save();

        $this->assertTrue($this->adapter->hasTable($table->getName()));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testAddPrimaryKey()
    {
        $table = new \Phinx\Db\Table('table1', ['id' => false], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey('column1')
            ->save();

        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testChangePrimaryKey()
    {
        $table = new \Phinx\Db\Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey('column2')
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2']));
    }

    public function testChangePrimaryKeyNonInteger()
    {
        $table = new \Phinx\Db\Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'string')
            ->addColumn('column2', 'string')
            ->save();

        $table
            ->changePrimaryKey('column2')
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2']));
    }

    public function testDropPrimaryKey()
    {
        $table = new \Phinx\Db\Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddMultipleColumnPrimaryKeyFails()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey(['column1', 'column2'])
            ->save();
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testChangeCommentFails()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment1')
            ->save();
    }

    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
            ->save();
        $this->assertTrue($table->hasColumn('email'));

        // In SQLite it is not possible to dictate order of added columns.
        // $table->addColumn('realname', 'string', array('after' => 'id'))
        //       ->save();
        // $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("'test'", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNotNull($rows[1]['dflt_value']);
        $this->assertEquals("0", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("''", $rows[1]['dflt_value']);
    }

    public function testAddDoubleColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('foo', 'double')
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals('DOUBLE', $rows[1]['type']);
    }

    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
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
            $this->assertEquals('The specified column doesn\'t exist: column2', $e->getMessage());
        }
    }

    public function testChangeColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn2 = new \Phinx\Db\Table\Column();
        $newColumn2->setName('column2')
            ->setType('string');
        $table->changeColumn('column1', $newColumn2)->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault('test1')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');

        $this->assertEquals("'test1'", $rows[1]['dflt_value']);
    }

    /**
     * @group bug922
     */
    public function testChangeColumnWithForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $table->changeColumn('ref_table_id', 'float')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault(0)
            ->setType('integer');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals("0", $rows[1]['dflt_value']);
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault(null)
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertNull($rows[1]['dflt_value']);
    }

    public function testChangeColumnWithCommasInCommentsOrDefaultValue()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'one, two or three', 'comment' => 'three, two or one'])
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setDefault('another default')
            ->setComment('another comment')
            ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $cols = $this->adapter->getColumns('t');
        $this->assertEquals('another default', (string)$cols[1]->getDefault());
    }

    /**
     * @dataProvider columnCreationArgumentProvider
     */
    public function testDropColumn($columnCreationArgs)
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $columnName = $columnCreationArgs[0];
        call_user_func_array([$table, 'addColumn'], $columnCreationArgs);
        $table->save();
        $this->assertTrue($this->adapter->hasColumn('t', $columnName));

        $table->removeColumn($columnName)->save();

        $this->assertFalse($this->adapter->hasColumn('t', $columnName));
    }

    public function columnCreationArgumentProvider()
    {
        return [
            [['column1', 'string']],
            [['profile_colour', 'integer']],
        ];
    }

    public function columnsProvider()
    {
        return [
            ['column1', 'string', []],
            ['column2', 'integer', []],
            ['column3', 'biginteger', []],
            ['column4', 'text', []],
            ['column5', 'float', []],
            ['column7', 'datetime', []],
            ['column8', 'time', []],
            ['column9', 'timestamp', []],
            ['column10', 'date', []],
            ['column11', 'binary', []],
            ['column13', 'string', ['limit' => 10]],
            ['column15', 'smallinteger', []],
            ['column15', 'integer', []],
            ['column23', 'json', []],
        ];
    }

    public function testAddIndex()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
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
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'])
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table2->getName(), ['fname', 'lname']);
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // single column index with name specified
        $table3 = new \Phinx\Db\Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'someindexname'])
            ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('table4', [], $this->adapter);
        $table4->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
            ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table4->getName(), ['fname', 'lname']);
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailindex'])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'], ['name' => 'twocolumnindex'])
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testAddForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')
            ->addIndex(['field1'], ['unique' => true])
            ->save();

        $table = new \Phinx\Db\Table('another_table', [], $this->adapter);
        $opts = [
            'update' => 'CASCADE',
            'delete' => 'CASCADE',
        ];
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addColumn('ref_table_field', 'string')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->addForeignKey(['ref_table_field'], 'ref_table', ['field1'], $opts)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_field']));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_field']);
        $this->assertTrue($this->adapter->hasTable($table->getName()));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /test/
     */
    public function testFailingDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new \Phinx\Db\Table('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id', 'test']);
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_SQLITE_DATABASE));
    }

    public function testDropDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->createDatabase('phinx_temp_database');
        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->dropDatabase('phinx_temp_database');
    }

    public function testAddColumnWithComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => $comment = 'Comments from "column1"'])
            ->save();

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');

        foreach ($rows as $row) {
            if ($row['tbl_name'] == 'table1') {
                $sql = $row['sql'];
            }
        }

        $this->assertRegExp('/\/\* Comments from "column1" \*\//', $sql);
    }

    public function testPhinxTypeLiteral()
    {
        $this->assertEquals(
            [
                'name' => Literal::from('fake'),
                'limit' => null,
                'scale' => null,
            ],
            $this->adapter->getPhinxType('fake')
        );
    }

    public function testPhinxTypeNotValidTypeRegex()
    {
        $exp = [
            'name' => Literal::from('?int?'),
            'limit' => null,
            'scale' => null,
        ];
        $this->assertEquals($exp, $this->adapter->getPhinxType('?int?'));
    }

    public function testAddIndexTwoTablesSameIndex()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('email', 'string')
            ->save();

        $this->assertFalse($table->hasIndex('email'));
        $this->assertFalse($table2->hasIndex('email'));

        $table->addIndex('email')
            ->save();
        $table2->addIndex('email')
            ->save();

        $this->assertTrue($table->hasIndex('email'));
        $this->assertTrue($table2->hasIndex('email'));
    }

    public function testBulkInsertData()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
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
                ],
            ])
            ->insert(
                [
                    'column1' => 'value3',
                    'column2' => 3,
                ]
            )
            ->insert(
                [
                    'column1' => '\'value4\'',
                    'column2' => null,
                ]
            )
            ->save();
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('\'value4\'', $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals(null, $rows[3]['column2']);
    }

    public function testInsertData()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
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
                ],
            ])
            ->insert(
                [
                    'column1' => 'value3',
                    'column2' => 3,
                ]
            )
            ->insert(
                [
                    'column1' => '\'value4\'',
                    'column2' => null,
                ]
            )
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals('\'value4\'', $rows[3]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals(null, $rows[3]['column2']);
    }

    public function testBulkInsertDataEnum()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'string', ['null' => true])
            ->addColumn('column3', 'string', ['default' => 'c'])
            ->insert([
                'column1' => 'a',
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('a', $rows[0]['column1']);
        $this->assertEquals(null, $rows[0]['column2']);
        $this->assertEquals('c', $rows[0]['column3']);
    }

    public function testNullWithoutDefaultValue()
    {
        $this->markTestSkipped('Skipping for now. See Github Issue #265.');

        // construct table with default/null combinations
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn("aa", "string", ["null" => true]) // no default value
        ->addColumn("bb", "string", ["null" => false]) // no default value
        ->addColumn("cc", "string", ["null" => true, "default" => "some1"])
            ->addColumn("dd", "string", ["null" => false, "default" => "some2"])
            ->save();

        // load table info
        $columns = $this->adapter->getColumns("table1");

        $this->assertCount(5, $columns);

        $aa = $columns[1];
        $bb = $columns[2];
        $cc = $columns[3];
        $dd = $columns[4];

        $this->assertEquals("aa", $aa->getName());
        $this->assertEquals(true, $aa->isNull());
        $this->assertEquals(null, $aa->getDefault());

        $this->assertEquals("bb", $bb->getName());
        $this->assertEquals(false, $bb->isNull());
        $this->assertEquals(null, $bb->getDefault());

        $this->assertEquals("cc", $cc->getName());
        $this->assertEquals(true, $cc->isNull());
        $this->assertEquals("some1", $cc->getDefault());

        $this->assertEquals("dd", $dd->getName());
        $this->assertEquals(false, $dd->isNull());
        $this->assertEquals("some2", $dd->getDefault());
    }

    public function testDumpCreateTable()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $table = new \Phinx\Db\Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, `column1` VARCHAR NULL, `column2` INTEGER NULL, `column3` VARCHAR NOT NULL DEFAULT 'test');
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create table query to the output');
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts a record.
     * Asserts that phinx outputs the insert statement and doesn't insert a record.
     */
    public function testDumpInsert()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $this->adapter->insert($table->getTable(), [
            'string_col' => 'test data',
        ]);

        $this->adapter->insert($table->getTable(), [
            'string_col' => null,
        ]);

        $this->adapter->insert($table->getTable(), [
            'int_col' => 23,
        ]);

        $expectedOutput = <<<'OUTPUT'
INSERT INTO `table1` (`string_col`) VALUES ('test data');
INSERT INTO `table1` (`string_col`) VALUES (null);
INSERT INTO `table1` (`int_col`) VALUES (23);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertContains($expectedOutput, $actualOutput, 'Passing the --dry-run option doesn\'t dump the insert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        self::assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0]['COUNT(*)']);
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts some records.
     * Asserts that phinx outputs the insert statement and doesn't insert any record.
     */
    public function testDumpBulkinsert()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $this->adapter->bulkinsert($table->getTable(), [
            [
                'string_col' => 'test_data1',
                'int_col' => 23,
            ],
            [
                'string_col' => null,
                'int_col' => 42,
            ],
        ]);

        $expectedOutput = <<<'OUTPUT'
INSERT INTO `table1` (`string_col`, `int_col`) VALUES ('test_data1', 23), (null, 42);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains($expectedOutput, $actualOutput, 'Passing the --dry-run option doesn\'t dump the bulkinsert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        self::assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0]['COUNT(*)']);
    }

    public function testDumpCreateTableAndThenInsert()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $table = new \Phinx\Db\Table('table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->save();

        $expectedOutput = 'C';

        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`column1` VARCHAR NULL, `column2` INTEGER NULL, PRIMARY KEY (`column1`));
INSERT INTO `table1` (`column1`, `column2`) VALUES ('id1', 1);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
    }

    /**
     * Tests interaction with the query builder
     *
     */
    public function testQueryBuilder()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $builder = $this->adapter->getQueryBuilder();
        $stm = $builder
            ->insert(['string_col', 'int_col'])
            ->into('table1')
            ->values(['string_col' => 'value1', 'int_col' => 1])
            ->values(['string_col' => 'value2', 'int_col' => 2])
            ->execute();

        $this->assertEquals(2, $stm->rowCount());

        $builder = $this->adapter->getQueryBuilder();
        $stm = $builder
            ->select('*')
            ->from('table1')
            ->where(['int_col >=' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
        $this->assertEquals(
            ['id' => 2, 'string_col' => 'value2', 'int_col' => '2'],
            $stm->fetch('assoc')
        );

        $builder = $this->adapter->getQueryBuilder();
        $stm = $builder
            ->delete('table1')
            ->where(['int_col <' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
    }

    /**
     * Tests adding more than one column to a table
     * that already exists due to adapters having different add column instructions
     */
    public function testAlterTableColumnAdd()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();

        $table->addColumn('string_col', 'string');
        $table->addColumn('string_col_2', 'string');
        $table->save();
        $this->assertTrue($this->adapter->hasColumn('table1', 'string_col'));
        $this->assertTrue($this->adapter->hasColumn('table1', 'string_col_2'));
    }

    public function testLiteralSupport()
    {
        $createQuery = <<<'INPUT'
CREATE TABLE `test` (`real_col` DECIMAL)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new \Phinx\Db\Table('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('decimal'), array_pop($columns)->getType());
    }

    /** @dataProvider provideTableNamesForPresenceCheck
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasTable
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::resolveTable
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::quoteString
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     */
    public function testHasTable($createName, $tableName, $exp)
    {
        // Test case for issue #1535
        $conn = $this->adapter->getConnection();
        $conn->exec('ATTACH DATABASE \':memory:\' as etc');
        $conn->exec('ATTACH DATABASE \':memory:\' as "main.db"');
        $conn->exec(sprintf('DROP TABLE IF EXISTS %s', $createName));
        $this->assertFalse($this->adapter->hasTable($tableName), sprintf('Adapter claims table %s exists when it does not', $tableName));
        $conn->exec(sprintf('CREATE TABLE %s (a text)', $createName));
        if ($exp == true) {
            $this->assertTrue($this->adapter->hasTable($tableName), sprintf('Adapter claims table %s does not exist when it does', $tableName));
        } else {
            $this->assertFalse($this->adapter->hasTable($tableName), sprintf('Adapter claims table %s exists when it does not', $tableName));
        }
    }

    public function provideTableNamesForPresenceCheck()
    {
        return [
            'Ordinary table' => ['t', 't', true],
            'Ordinary table with schema' => ['t', 'main.t', true],
            'Temporary table' => ['temp.t', 't', true],
            'Temporary table with schema' => ['temp.t', 'temp.t', true],
            'Attached table' => ['etc.t', 't', true],
            'Attached table with schema' => ['etc.t', 'etc.t', true],
            'Attached table with unusual schema' => ['"main.db".t', 'main.db.t', true],
            'Wrong schema 1' => ['t', 'etc.t', false],
            'Wrong schema 2' => ['t', 'temp.t', false],
            'Missing schema' => ['t', 'not_attached.t', false],
            'Malicious table' => ['"\'"', '\'', true],
            'Malicious missing table' => ['t', '\'', false],
            'Table name case 1' => ['t', 'T', true],
            'Table name case 2' => ['T', 't', true],
            'Schema name case 1' => ['main.t', 'MAIN.t', true],
            'Schema name case 2' => ['MAIN.t', 'main.t', true],
            'Schema name case 3' => ['temp.t', 'TEMP.t', true],
            'Schema name case 4' => ['TEMP.t', 'temp.t', true],
            'Schema name case 5' => ['etc.t', 'ETC.t', true],
            'Schema name case 6' => ['ETC.t', 'etc.t', true],
            'PHP zero string 1' => ['"0"', '0', true],
            'PHP zero string 2' => ['"0"', '0e2', false],
            'PHP zero string 3' => ['"0e2"', '0', false],
        ];
    }

    /** @dataProvider provideIndexColumnsToCheck
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getIndexes
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::resolveIndex
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasIndex
     */
    public function testHasIndex($tableDef, $cols, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $this->assertEquals($exp, $this->adapter->hasIndex('t', $cols));
    }

    public function provideIndexColumnsToCheck()
    {
        return [
            ['create table t(a text)', 'a', false],
            ['create table t(a text); create index test on t(a);', 'a', true],
            ['create table t(a text unique)', 'a', true],
            ['create table t(a text primary key)', 'a', true],
            ['create table t(a text unique, b text unique)', ['a', 'b'], false],
            ['create table t(a text, b text, unique(a,b))', ['a', 'b'], true],
            ['create table t(a text, b text); create index test on t(a,b)', ['a', 'b'], true],
            ['create table t(a text, b text); create index test on t(a,b)', ['b', 'a'], false],
            ['create table t(a text, b text); create index test on t(a,b)', ['a'], false],
            ['create table t(a text, b text); create index test on t(a)', ['a', 'b'], false],
            ['create table t(a text, b text); create index test on t(a,b)', ['A', 'B'], true],
            ['create table t("A" text, "B" text); create index test on t("A","B")', ['a', 'b'], true],
            ['create table not_t(a text, b text, unique(a,b))', ['A', 'B'], false], // test checks table t which does not exist
            ['create table t(a text, b text); create index test on t(a)', ['a', 'a'], false],
            ['create table t(a text unique); create temp table t(a text)', 'a', false],
        ];
    }

    /** @dataProvider provideIndexNamesToCheck
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getIndexes
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasIndexByName
     */
    public function testHasIndexByName($tableDef, $index, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $this->assertEquals($exp, $this->adapter->hasIndexByName('t', $index));
    }

    public function provideIndexNamesToCheck()
    {
        return [
            ['create table t(a text)', 'test', false],
            ['create table t(a text); create index test on t(a);', 'test', true],
            ['create table t(a text); create index test on t(a);', 'TEST', true],
            ['create table t(a text); create index "TEST" on t(a);', 'test', true],
            ['create table t(a text unique)', 'sqlite_autoindex_t_1', true],
            ['create table t(a text primary key)', 'sqlite_autoindex_t_1', true],
            ['create table not_t(a text); create index test on not_t(a);', 'test', false], // test checks table t which does not exist
            ['create table t(a text unique); create temp table t(a text)', 'sqlite_autoindex_t_1', false],
        ];
    }

    /** @dataProvider providePrimaryKeysToCheck
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasPrimaryKey
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getPrimaryKey
     */
    public function testHasPrimaryKey($tableDef, $key, $exp)
    {
        $this->assertFalse($this->adapter->hasTable('t'), 'Dirty test fixture');
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $this->assertSame($exp, $this->adapter->hasPrimaryKey('t', $key));
    }

    public function providePrimaryKeysToCheck()
    {
        return [
            ['create table t(a integer)', 'a', false],
            ['create table t(a integer)', [], true],
            ['create table t(a integer primary key)', 'a', true],
            ['create table t(a integer primary key)', [], false],
            ['create table t(a integer PRIMARY KEY)', 'a', true],
            ['create table t(`a` integer PRIMARY KEY)', 'a', true],
            ['create table t("a" integer PRIMARY KEY)', 'a', true],
            ['create table t([a] integer PRIMARY KEY)', 'a', true],
            ['create table t(`a` integer PRIMARY KEY)', 'a', true],
            ['create table t(\'a\' integer PRIMARY KEY)', 'a', true],
            ['create table t(`a.a` integer PRIMARY KEY)', 'a.a', true],
            ['create table t(a integer primary key)', ['a'], true],
            ['create table t(a integer primary key)', ['a', 'b'], false],
            ['create table t(a integer, primary key(a))', 'a', true],
            ['create table t(a integer, primary key("a"))', 'a', true],
            ['create table t(a integer, primary key([a]))', 'a', true],
            ['create table t(a integer, primary key(`a`))', 'a', true],
            ['create table t(a integer, b integer primary key)', 'a', false],
            ['create table t(a integer, b text primary key)', 'b', true],
            ['create table t(a integer, b integer default 2112 primary key)', ['a'], false],
            ['create table t(a integer, b integer primary key)', ['b'], true],
            ['create table t(a integer, b integer primary key)', ['b', 'b'], true], // duplicate column is collapsed
            ['create table t(a integer, b integer, primary key(a,b))', ['b', 'a'], true],
            ['create table t(a integer, b integer, primary key(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, primary key(a,b))', 'a', false],
            ['create table t(a integer, b integer, primary key(a,b))', ['a'], false],
            ['create table t(a integer, b integer, primary key(a,b))', ['a', 'b', 'c'], false],
            ['create table t(a integer, b integer, primary key(a,b))', ['a', 'B'], true],
            ['create table t(a integer, "B" integer, primary key(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, constraint t_pk primary key(a,b))', ['a', 'b'], true],
            ['create table t(a integer); create temp table t(a integer primary key)', 'a', true],
            ['create temp table t(a integer primary key)', 'a', true],
            ['create table t("0" integer primary key)', ['0'], true],
            ['create table t("0" integer primary key)', ['0e0'], false],
            ['create table t("0e0" integer primary key)', ['0'], false],
            ['create table not_t(a integer)', 'a', false] // test checks table t which does not exist
        ];
    }

    /** @covers \Phinx\Db\Adapter\SQLiteAdapter::hasPrimaryKey */
    public function testHasNamedPrimaryKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->hasPrimaryKey('t', [], 'named_constraint');
    }

    /** @dataProvider provideForeignKeysToCheck
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasForeignKey
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getForeignKeys
     */
    public function testHasForeignKey($tableDef, $key, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec('CREATE TABLE other(a integer, b integer, c integer)');
        $conn->exec($tableDef);
        $this->assertSame($exp, $this->adapter->hasForeignKey('t', $key));
    }

    public function provideForeignKeysToCheck()
    {
        return [
            ['create table t(a integer)', 'a', false],
            ['create table t(a integer)', [], false],
            ['create table t(a integer primary key)', 'a', false],
            ['create table t(a integer references other(a))', 'a', true],
            ['create table t(a integer references other(b))', 'a', true],
            ['create table t(a integer references other(b))', ['a'], true],
            ['create table t(a integer references other(b))', ['a', 'a'], true], // duplicate column is collapsed
            ['create table t(a integer, foreign key(a) references other(a))', 'a', true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', 'a', false],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['b', 'a'], true],
            ['create table t(a integer, "B" integer, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['a', 'B'], true],
            ['create table t(a integer, b integer, c integer, foreign key(a,b,c) references other(a,b,c))', ['a', 'b'], false],
            ['create table t(a integer, foreign key(a) references other(a))', ['a', 'b'], false],
            ['create table t(a integer references other(a), b integer references other(b))', ['a', 'b'], false],
            ['create table t(a integer references other(a), b integer references other(b))', ['a', 'b'], false],
            ['create table t(a integer); create temp table t(a integer references other(a))', ['a'], true],
            ['create temp table t(a integer references other(a))', ['a'], true],
            ['create table t("0" integer references other(a))', '0', true],
            ['create table t("0" integer references other(a))', '0e0', false],
            ['create table t("0e0" integer references other(a))', '0', false],
        ];
    }

    /** @covers \Phinx\Db\Adapter\SQLiteAdapter::hasForeignKey */
    public function testHasNamedForeignKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->hasForeignKey('t', [], 'named_constraint');
    }

    /** @dataProvider providePhinxTypes
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSqlType
     */
    public function testGetSqlType($phinxType, $limit, $exp)
    {
        if ($exp instanceof \Exception) {
            $this->expectException(get_class($exp));
            $this->adapter->getSqlType($phinxType, $limit);
        } else {
            $exp = ['name' => $exp, 'limit' => $limit];
            $this->assertEquals($exp, $this->adapter->getSqlType($phinxType, $limit));
        }
    }

    public function providePhinxTypes()
    {
        $unsupported = new \Phinx\Db\Adapter\UnsupportedColumnTypeException();

        return [
            [SQLiteAdapter::PHINX_TYPE_BIG_INTEGER, null, SQLiteAdapter::PHINX_TYPE_BIG_INTEGER],
            [SQLiteAdapter::PHINX_TYPE_BINARY, null, SQLiteAdapter::PHINX_TYPE_BINARY . '_blob'],
            [SQLiteAdapter::PHINX_TYPE_BIT, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_BLOB, null, SQLiteAdapter::PHINX_TYPE_BLOB],
            [SQLiteAdapter::PHINX_TYPE_BOOLEAN, null, SQLiteAdapter::PHINX_TYPE_BOOLEAN . '_integer'],
            [SQLiteAdapter::PHINX_TYPE_CHAR, null, SQLiteAdapter::PHINX_TYPE_CHAR],
            [SQLiteAdapter::PHINX_TYPE_CIDR, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_DATE, null, SQLiteAdapter::PHINX_TYPE_DATE . '_text'],
            [SQLiteAdapter::PHINX_TYPE_DATETIME, null, SQLiteAdapter::PHINX_TYPE_DATETIME . '_text'],
            [SQLiteAdapter::PHINX_TYPE_DECIMAL, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_DOUBLE, null, SQLiteAdapter::PHINX_TYPE_DOUBLE],
            [SQLiteAdapter::PHINX_TYPE_ENUM, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_FILESTREAM, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_FLOAT, null, SQLiteAdapter::PHINX_TYPE_FLOAT],
            [SQLiteAdapter::PHINX_TYPE_GEOMETRY, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_INET, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_INTEGER, null, SQLiteAdapter::PHINX_TYPE_INTEGER],
            [SQLiteAdapter::PHINX_TYPE_INTERVAL, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_JSON, null, SQLiteAdapter::PHINX_TYPE_JSON . '_text'],
            [SQLiteAdapter::PHINX_TYPE_JSONB, null, SQLiteAdapter::PHINX_TYPE_JSONB . '_text'],
            [SQLiteAdapter::PHINX_TYPE_LINESTRING, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_MACADDR, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_POINT, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_POLYGON, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_SET, null, $unsupported],
            [SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, null, SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER],
            [SQLiteAdapter::PHINX_TYPE_STRING, null, 'varchar'],
            [SQLiteAdapter::PHINX_TYPE_TEXT, null, SQLiteAdapter::PHINX_TYPE_TEXT],
            [SQLiteAdapter::PHINX_TYPE_TIME, null, SQLiteAdapter::PHINX_TYPE_TIME . '_text'],
            [SQLiteAdapter::PHINX_TYPE_TIMESTAMP, null, SQLiteAdapter::PHINX_TYPE_TIMESTAMP . '_text'],
            [SQLiteAdapter::PHINX_TYPE_UUID, null, SQLiteAdapter::PHINX_TYPE_UUID . '_text'],
            [SQLiteAdapter::PHINX_TYPE_VARBINARY, null, SQLiteAdapter::PHINX_TYPE_VARBINARY . '_blob'],
            [SQLiteAdapter::PHINX_TYPE_STRING, 5, 'varchar'],
            [Literal::from('someType'), 5, Literal::from('someType')],
            ['notAType', null, $unsupported],
        ];
    }

    /** @dataProvider provideSqlTypes
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getPhinxType
     */
    public function testGetPhinxType($sqlType, $exp)
    {
        $this->assertEquals($exp, $this->adapter->getPhinxType($sqlType));
    }

    public function provideSqlTypes()
    {
        return [
            ['varchar', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => null, 'scale' => null]],
            ['string', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => null, 'scale' => null]],
            ['string_text', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => null, 'scale' => null]],
            ['varchar(5)', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => 5, 'scale' => null]],
            ['varchar(55,2)', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => 55, 'scale' => 2]],
            ['char', ['name' => SQLiteAdapter::PHINX_TYPE_CHAR, 'limit' => null, 'scale' => null]],
            ['boolean', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['boolean_integer', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['int', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['integer', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['tinyint', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['tinyint(1)', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['tinyinteger', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['tinyinteger(1)', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => 1, 'scale' => null]],
            ['smallint', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['smallinteger', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['mediumint', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['mediuminteger', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['bigint', ['name' => SQLiteAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'scale' => null]],
            ['biginteger', ['name' => SQLiteAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'scale' => null]],
            ['text', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['tinytext', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['mediumtext', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['longtext', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['blob', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['tinyblob', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['mediumblob', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['longblob', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['float', ['name' => SQLiteAdapter::PHINX_TYPE_FLOAT, 'limit' => null, 'scale' => null]],
            ['real', ['name' => SQLiteAdapter::PHINX_TYPE_FLOAT, 'limit' => null, 'scale' => null]],
            ['double', ['name' => SQLiteAdapter::PHINX_TYPE_DOUBLE, 'limit' => null, 'scale' => null]],
            ['date', ['name' => SQLiteAdapter::PHINX_TYPE_DATE, 'limit' => null, 'scale' => null]],
            ['date_text', ['name' => SQLiteAdapter::PHINX_TYPE_DATE, 'limit' => null, 'scale' => null]],
            ['datetime', ['name' => SQLiteAdapter::PHINX_TYPE_DATETIME, 'limit' => null, 'scale' => null]],
            ['datetime_text', ['name' => SQLiteAdapter::PHINX_TYPE_DATETIME, 'limit' => null, 'scale' => null]],
            ['time', ['name' => SQLiteAdapter::PHINX_TYPE_TIME, 'limit' => null, 'scale' => null]],
            ['time_text', ['name' => SQLiteAdapter::PHINX_TYPE_TIME, 'limit' => null, 'scale' => null]],
            ['timestamp', ['name' => SQLiteAdapter::PHINX_TYPE_TIMESTAMP, 'limit' => null, 'scale' => null]],
            ['timestamp_text', ['name' => SQLiteAdapter::PHINX_TYPE_TIMESTAMP, 'limit' => null, 'scale' => null]],
            ['binary', ['name' => SQLiteAdapter::PHINX_TYPE_BINARY, 'limit' => null, 'scale' => null]],
            ['binary_blob', ['name' => SQLiteAdapter::PHINX_TYPE_BINARY, 'limit' => null, 'scale' => null]],
            ['varbinary', ['name' => SQLiteAdapter::PHINX_TYPE_VARBINARY, 'limit' => null, 'scale' => null]],
            ['varbinary_blob', ['name' => SQLiteAdapter::PHINX_TYPE_VARBINARY, 'limit' => null, 'scale' => null]],
            ['json', ['name' => SQLiteAdapter::PHINX_TYPE_JSON, 'limit' => null, 'scale' => null]],
            ['json_text', ['name' => SQLiteAdapter::PHINX_TYPE_JSON, 'limit' => null, 'scale' => null]],
            ['jsonb', ['name' => SQLiteAdapter::PHINX_TYPE_JSONB, 'limit' => null, 'scale' => null]],
            ['jsonb_text', ['name' => SQLiteAdapter::PHINX_TYPE_JSONB, 'limit' => null, 'scale' => null]],
            ['uuid', ['name' => SQLiteAdapter::PHINX_TYPE_UUID, 'limit' => null, 'scale' => null]],
            ['uuid_text', ['name' => SQLiteAdapter::PHINX_TYPE_UUID, 'limit' => null, 'scale' => null]],
            ['decimal', ['name' => Literal::from('decimal'), 'limit' => null, 'scale' => null]],
            ['point', ['name' => Literal::from('point'), 'limit' => null, 'scale' => null]],
            ['polygon', ['name' => Literal::from('polygon'), 'limit' => null, 'scale' => null]],
            ['linestring', ['name' => Literal::from('linestring'), 'limit' => null, 'scale' => null]],
            ['geometry', ['name' => Literal::from('geometry'), 'limit' => null, 'scale' => null]],
            ['bit', ['name' => Literal::from('bit'), 'limit' => null, 'scale' => null]],
            ['enum', ['name' => Literal::from('enum'), 'limit' => null, 'scale' => null]],
            ['set', ['name' => Literal::from('set'), 'limit' => null, 'scale' => null]],
            ['cidr', ['name' => Literal::from('cidr'), 'limit' => null, 'scale' => null]],
            ['inet', ['name' => Literal::from('inet'), 'limit' => null, 'scale' => null]],
            ['macaddr', ['name' => Literal::from('macaddr'), 'limit' => null, 'scale' => null]],
            ['interval', ['name' => Literal::from('interval'), 'limit' => null, 'scale' => null]],
            ['filestream', ['name' => Literal::from('filestream'), 'limit' => null, 'scale' => null]],
            ['decimal_text', ['name' => Literal::from('decimal'), 'limit' => null, 'scale' => null]],
            ['point_text', ['name' => Literal::from('point'), 'limit' => null, 'scale' => null]],
            ['polygon_text', ['name' => Literal::from('polygon'), 'limit' => null, 'scale' => null]],
            ['linestring_text', ['name' => Literal::from('linestring'), 'limit' => null, 'scale' => null]],
            ['geometry_text', ['name' => Literal::from('geometry'), 'limit' => null, 'scale' => null]],
            ['bit_text', ['name' => Literal::from('bit'), 'limit' => null, 'scale' => null]],
            ['enum_text', ['name' => Literal::from('enum'), 'limit' => null, 'scale' => null]],
            ['set_text', ['name' => Literal::from('set'), 'limit' => null, 'scale' => null]],
            ['cidr_text', ['name' => Literal::from('cidr'), 'limit' => null, 'scale' => null]],
            ['inet_text', ['name' => Literal::from('inet'), 'limit' => null, 'scale' => null]],
            ['macaddr_text', ['name' => Literal::from('macaddr'), 'limit' => null, 'scale' => null]],
            ['interval_text', ['name' => Literal::from('interval'), 'limit' => null, 'scale' => null]],
            ['filestream_text', ['name' => Literal::from('filestream'), 'limit' => null, 'scale' => null]],
            ['bit_text(2,12)', ['name' => Literal::from('bit'), 'limit' => 2, 'scale' => 12]],
            ['VARCHAR', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => null, 'scale' => null]],
            ['STRING', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => null, 'scale' => null]],
            ['STRING_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => null, 'scale' => null]],
            ['VARCHAR(5)', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => 5, 'scale' => null]],
            ['VARCHAR(55,2)', ['name' => SQLiteAdapter::PHINX_TYPE_STRING, 'limit' => 55, 'scale' => 2]],
            ['CHAR', ['name' => SQLiteAdapter::PHINX_TYPE_CHAR, 'limit' => null, 'scale' => null]],
            ['BOOLEAN', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['BOOLEAN_INTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['INT', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['INTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['TINYINT', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['TINYINT(1)', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['TINYINTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['TINYINTEGER(1)', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => 1, 'scale' => null]],
            ['SMALLINT', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['SMALLINTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['MEDIUMINT', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['MEDIUMINTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['BIGINT', ['name' => SQLiteAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'scale' => null]],
            ['BIGINTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'scale' => null]],
            ['TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['TINYTEXT', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['MEDIUMTEXT', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['LONGTEXT', ['name' => SQLiteAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'scale' => null]],
            ['BLOB', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['TINYBLOB', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['MEDIUMBLOB', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['LONGBLOB', ['name' => SQLiteAdapter::PHINX_TYPE_BLOB, 'limit' => null, 'scale' => null]],
            ['FLOAT', ['name' => SQLiteAdapter::PHINX_TYPE_FLOAT, 'limit' => null, 'scale' => null]],
            ['REAL', ['name' => SQLiteAdapter::PHINX_TYPE_FLOAT, 'limit' => null, 'scale' => null]],
            ['DOUBLE', ['name' => SQLiteAdapter::PHINX_TYPE_DOUBLE, 'limit' => null, 'scale' => null]],
            ['DATE', ['name' => SQLiteAdapter::PHINX_TYPE_DATE, 'limit' => null, 'scale' => null]],
            ['DATE_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_DATE, 'limit' => null, 'scale' => null]],
            ['DATETIME', ['name' => SQLiteAdapter::PHINX_TYPE_DATETIME, 'limit' => null, 'scale' => null]],
            ['DATETIME_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_DATETIME, 'limit' => null, 'scale' => null]],
            ['TIME', ['name' => SQLiteAdapter::PHINX_TYPE_TIME, 'limit' => null, 'scale' => null]],
            ['TIME_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_TIME, 'limit' => null, 'scale' => null]],
            ['TIMESTAMP', ['name' => SQLiteAdapter::PHINX_TYPE_TIMESTAMP, 'limit' => null, 'scale' => null]],
            ['TIMESTAMP_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_TIMESTAMP, 'limit' => null, 'scale' => null]],
            ['BINARY', ['name' => SQLiteAdapter::PHINX_TYPE_BINARY, 'limit' => null, 'scale' => null]],
            ['BINARY_BLOB', ['name' => SQLiteAdapter::PHINX_TYPE_BINARY, 'limit' => null, 'scale' => null]],
            ['VARBINARY', ['name' => SQLiteAdapter::PHINX_TYPE_VARBINARY, 'limit' => null, 'scale' => null]],
            ['VARBINARY_BLOB', ['name' => SQLiteAdapter::PHINX_TYPE_VARBINARY, 'limit' => null, 'scale' => null]],
            ['JSON', ['name' => SQLiteAdapter::PHINX_TYPE_JSON, 'limit' => null, 'scale' => null]],
            ['JSON_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_JSON, 'limit' => null, 'scale' => null]],
            ['JSONB', ['name' => SQLiteAdapter::PHINX_TYPE_JSONB, 'limit' => null, 'scale' => null]],
            ['JSONB_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_JSONB, 'limit' => null, 'scale' => null]],
            ['UUID', ['name' => SQLiteAdapter::PHINX_TYPE_UUID, 'limit' => null, 'scale' => null]],
            ['UUID_TEXT', ['name' => SQLiteAdapter::PHINX_TYPE_UUID, 'limit' => null, 'scale' => null]],
            ['DECIMAL', ['name' => Literal::from('decimal'), 'limit' => null, 'scale' => null]],
            ['POINT', ['name' => Literal::from('point'), 'limit' => null, 'scale' => null]],
            ['POLYGON', ['name' => Literal::from('polygon'), 'limit' => null, 'scale' => null]],
            ['LINESTRING', ['name' => Literal::from('linestring'), 'limit' => null, 'scale' => null]],
            ['GEOMETRY', ['name' => Literal::from('geometry'), 'limit' => null, 'scale' => null]],
            ['BIT', ['name' => Literal::from('bit'), 'limit' => null, 'scale' => null]],
            ['ENUM', ['name' => Literal::from('enum'), 'limit' => null, 'scale' => null]],
            ['SET', ['name' => Literal::from('set'), 'limit' => null, 'scale' => null]],
            ['CIDR', ['name' => Literal::from('cidr'), 'limit' => null, 'scale' => null]],
            ['INET', ['name' => Literal::from('inet'), 'limit' => null, 'scale' => null]],
            ['MACADDR', ['name' => Literal::from('macaddr'), 'limit' => null, 'scale' => null]],
            ['INTERVAL', ['name' => Literal::from('interval'), 'limit' => null, 'scale' => null]],
            ['FILESTREAM', ['name' => Literal::from('filestream'), 'limit' => null, 'scale' => null]],
            ['DECIMAL_TEXT', ['name' => Literal::from('decimal'), 'limit' => null, 'scale' => null]],
            ['POINT_TEXT', ['name' => Literal::from('point'), 'limit' => null, 'scale' => null]],
            ['POLYGON_TEXT', ['name' => Literal::from('polygon'), 'limit' => null, 'scale' => null]],
            ['LINESTRING_TEXT', ['name' => Literal::from('linestring'), 'limit' => null, 'scale' => null]],
            ['GEOMETRY_TEXT', ['name' => Literal::from('geometry'), 'limit' => null, 'scale' => null]],
            ['BIT_TEXT', ['name' => Literal::from('bit'), 'limit' => null, 'scale' => null]],
            ['ENUM_TEXT', ['name' => Literal::from('enum'), 'limit' => null, 'scale' => null]],
            ['SET_TEXT', ['name' => Literal::from('set'), 'limit' => null, 'scale' => null]],
            ['CIDR_TEXT', ['name' => Literal::from('cidr'), 'limit' => null, 'scale' => null]],
            ['INET_TEXT', ['name' => Literal::from('inet'), 'limit' => null, 'scale' => null]],
            ['MACADDR_TEXT', ['name' => Literal::from('macaddr'), 'limit' => null, 'scale' => null]],
            ['INTERVAL_TEXT', ['name' => Literal::from('interval'), 'limit' => null, 'scale' => null]],
            ['FILESTREAM_TEXT', ['name' => Literal::from('filestream'), 'limit' => null, 'scale' => null]],
            ['BIT_TEXT(2,12)', ['name' => Literal::from('bit'), 'limit' => 2, 'scale' => 12]],
            ['not a type', ['name' => Literal::from('not a type'), 'limit' => null, 'scale' => null]],
            ['NOT A TYPE', ['name' => Literal::from('NOT A TYPE'), 'limit' => null, 'scale' => null]],
            ['not a type(2)', ['name' => Literal::from('not a type(2)'), 'limit' => null, 'scale' => null]],
            ['NOT A TYPE(2)', ['name' => Literal::from('NOT A TYPE(2)'), 'limit' => null, 'scale' => null]],
            ['ack', ['name' => Literal::from('ack'), 'limit' => null, 'scale' => null]],
            ['ACK', ['name' => Literal::from('ACK'), 'limit' => null, 'scale' => null]],
            ['ack_text', ['name' => Literal::from('ack_text'), 'limit' => null, 'scale' => null]],
            ['ACK_TEXT', ['name' => Literal::from('ACK_TEXT'), 'limit' => null, 'scale' => null]],
            ['ack_text(2,12)', ['name' => Literal::from('ack_text'), 'limit' => 2, 'scale' => 12]],
            ['ACK_TEXT(12,2)', ['name' => Literal::from('ACK_TEXT'), 'limit' => 12, 'scale' => 2]],
            [null, ['name' => null, 'limit' => null, 'scale' => null]],
        ];
    }

    /** @covers \Phinx\Db\Adapter\SQLiteAdapter::getColumnTypes */
    public function testGetColumnTypes()
    {
        $exp = [
            SQLiteAdapter::PHINX_TYPE_BIG_INTEGER,
            SQLiteAdapter::PHINX_TYPE_BINARY,
            SQLiteAdapter::PHINX_TYPE_BLOB,
            SQLiteAdapter::PHINX_TYPE_BOOLEAN,
            SQLiteAdapter::PHINX_TYPE_CHAR,
            SQLiteAdapter::PHINX_TYPE_DATE,
            SQLiteAdapter::PHINX_TYPE_DATETIME,
            SQLiteAdapter::PHINX_TYPE_DOUBLE,
            SQLiteAdapter::PHINX_TYPE_FLOAT,
            SQLiteAdapter::PHINX_TYPE_INTEGER,
            SQLiteAdapter::PHINX_TYPE_JSON,
            SQLiteAdapter::PHINX_TYPE_JSONB,
            SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER,
            SQLiteAdapter::PHINX_TYPE_STRING,
            SQLiteAdapter::PHINX_TYPE_TEXT,
            SQLiteAdapter::PHINX_TYPE_TIME,
            SQLiteAdapter::PHINX_TYPE_UUID,
            SQLiteAdapter::PHINX_TYPE_TIMESTAMP,
            SQLiteAdapter::PHINX_TYPE_VARBINARY,
        ];
        $this->assertEquals($exp, $this->adapter->getColumnTypes());
    }

    /** @dataProvider provideColumnTypesForValidation
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::isValidColumnType
     */
    public function testIsValidColumnType($phinxType, $exp)
    {
        $col = (new Column())->setType($phinxType);
        $this->assertSame($exp, $this->adapter->isValidColumnType($col));
    }

    public function provideColumnTypesForValidation()
    {
        return [
            [SQLiteAdapter::PHINX_TYPE_BIG_INTEGER, true],
            [SQLiteAdapter::PHINX_TYPE_BINARY, true],
            [SQLiteAdapter::PHINX_TYPE_BLOB, true],
            [SQLiteAdapter::PHINX_TYPE_BOOLEAN, true],
            [SQLiteAdapter::PHINX_TYPE_CHAR, true],
            [SQLiteAdapter::PHINX_TYPE_DATE, true],
            [SQLiteAdapter::PHINX_TYPE_DATETIME, true],
            [SQLiteAdapter::PHINX_TYPE_DOUBLE, true],
            [SQLiteAdapter::PHINX_TYPE_FLOAT, true],
            [SQLiteAdapter::PHINX_TYPE_INTEGER, true],
            [SQLiteAdapter::PHINX_TYPE_JSON, true],
            [SQLiteAdapter::PHINX_TYPE_JSONB, true],
            [SQLiteAdapter::PHINX_TYPE_SMALL_INTEGER, true],
            [SQLiteAdapter::PHINX_TYPE_STRING, true],
            [SQLiteAdapter::PHINX_TYPE_TEXT, true],
            [SQLiteAdapter::PHINX_TYPE_TIME, true],
            [SQLiteAdapter::PHINX_TYPE_UUID, true],
            [SQLiteAdapter::PHINX_TYPE_TIMESTAMP, true],
            [SQLiteAdapter::PHINX_TYPE_VARBINARY, true],
            [SQLiteAdapter::PHINX_TYPE_BIT, false],
            [SQLiteAdapter::PHINX_TYPE_CIDR, false],
            [SQLiteAdapter::PHINX_TYPE_DECIMAL, false],
            [SQLiteAdapter::PHINX_TYPE_ENUM, false],
            [SQLiteAdapter::PHINX_TYPE_FILESTREAM, false],
            [SQLiteAdapter::PHINX_TYPE_GEOMETRY, false],
            [SQLiteAdapter::PHINX_TYPE_INET, false],
            [SQLiteAdapter::PHINX_TYPE_INTERVAL, false],
            [SQLiteAdapter::PHINX_TYPE_LINESTRING, false],
            [SQLiteAdapter::PHINX_TYPE_MACADDR, false],
            [SQLiteAdapter::PHINX_TYPE_POINT, false],
            [SQLiteAdapter::PHINX_TYPE_POLYGON, false],
            [SQLiteAdapter::PHINX_TYPE_SET, false],
            [Literal::from('someType'), true],
            ['someType', false],
        ];
    }

    /** @dataProvider provideDatabaseVersionStrings
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::databaseVersionAtLeast
     */
    public function testDatabaseVersionAtLeast($ver, $exp)
    {
        $this->assertSame($exp, $this->adapter->databaseVersionAtLeast($ver));
    }

    public function provideDatabaseVersionStrings()
    {
        return [
            ["2", true],
            ["3", true],
            ["4", false],
            ["3.0", true],
            ["3.0.0.0.0.0", true],
            ["3.0.0.0.0.99999", true],
            ["3.9999", false],
        ];
    }

    /** @dataProvider provideColumnNamesToCheck
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasColumn
     */
    public function testHasColumn($tableDef, $col, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $this->assertEquals($exp, $this->adapter->hasColumn('t', $col));
    }

    public function provideColumnNamesToCheck()
    {
        return [
            ['create table t(a text)', 'a', true],
            ['create table t(A text)', 'a', true],
            ['create table t("a" text)', 'a', true],
            ['create table t([a] text)', 'a', true],
            ['create table t(\'a\' text)', 'a', true],
            ['create table t("A" text)', 'a', true],
            ['create table t(a text)', 'A', true],
            ['create table t(b text)', 'a', false],
            ['create table t(b text, a text)', 'a', true],
            ['create table t("0" text)', '0', true],
            ['create table t("0" text)', '0e0', false],
            ['create table t("0e0" text)', '0', false],
            ['create table t("0" text)', 0, true],
            ['create table t(b text); create temp table t(a text)', 'a', true],
            ['create table not_t(a text)', 'a', false],
        ];
    }

    /** @covers \Phinx\Db\Adapter\SQLiteAdapter::getSchemaName
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getTableInfo
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getColumns
     */
    public function testGetColumns()
    {
        $conn = $this->adapter->getConnection();
        $conn->exec('create table t(a integer, b text, c char(5), d integer(12,6), e integer not null, f integer null)');
        $exp = [
            ['name' => 'a', 'type' => 'integer', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'b', 'type' => 'text', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'c', 'type' => 'char', 'null' => true, 'limit' => 5, 'precision' => 5, 'scale' => null],
            ['name' => 'd', 'type' => 'integer', 'null' => true, 'limit' => 12, 'precision' => 12, 'scale' => 6],
            ['name' => 'e', 'type' => 'integer', 'null' => false, 'limit' => null, 'precision' => null, 'scale' => null],
            ['name' => 'f', 'type' => 'integer', 'null' => true, 'limit' => null, 'precision' => null, 'scale' => null],
        ];
        $act = $this->adapter->getColumns('t');
        $this->assertCount(count($exp), $act);
        foreach ($exp as $index => $data) {
            $this->assertInstanceOf(Column::class, $act[$index]);
            foreach ($data as $key => $value) {
                $m = 'get' . ucfirst($key);
                $this->assertEquals($value, $act[$index]->$m(), "Parameter '$key' of column at index $index did not match expectations.");
            }
        }
    }

    /** @dataProvider provideIdentityCandidates
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::resolveIdentity
     */
    public function testGetColumnsForIdentity($tableDef, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $cols = $this->adapter->getColumns('t');
        $act = [];
        foreach ($cols as $col) {
            if ($col->getIdentity()) {
                $act[] = $col->getName();
            }
        }
        $this->assertEquals((array)$exp, $act);
    }

    public function provideIdentityCandidates()
    {
        return [
            ['create table t(a text)', null],
            ['create table t(a text primary key)', null],
            ['create table t(a integer, b text, primary key(a,b))', null],
            ['create table t(a integer primary key desc)', null],
            ['create table t(a integer primary key) without rowid', null],
            ['create table t(a integer primary key)', 'a'],
            ['CREATE TABLE T(A INTEGER PRIMARY KEY)', 'A'],
            ['create table t(a integer, primary key(a))', 'a'],
        ];
    }

    /** @dataProvider provideDefaultValues
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::parseDefaultValue
     */
    public function testGetColumnsForDefaults($tableDef, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $act = $this->adapter->getColumns('t')[0]->getDefault();
        if (is_object($exp)) {
            $this->assertEquals($exp, $act);
        } else {
            $this->assertSame($exp, $act);
        }
    }

    public function provideDefaultValues()
    {
        return [
            'Implicit null' => ['create table t(a integer)', null],
            'Explicit null LC' => ['create table t(a integer default null)', null],
            'Explicit null UC' => ['create table t(a integer default NULL)', null],
            'Explicit null MC' => ['create table t(a integer default nuLL)', null],
            'Extra parentheses' => ['create table t(a integer default ( ( null ) ))', null],
            'Comment 1' => ["create table t(a integer default ( /* this is perfectly fine */ null ))", null],
            'Comment 2' => ["create table t(a integer default ( /* this\nis\nperfectly\nfine */ null ))", null],
            'Line comment 1' => ["create table t(a integer default ( -- this is perfectly fine, too\n null ))", null],
            'Line comment 2' => ["create table t(a integer default ( -- this is perfectly fine, too\r\n null ))", null],
            'Current date LC' => ['create table t(a date default current_date)', "CURRENT_DATE"],
            'Current date UC' => ['create table t(a date default CURRENT_DATE)', "CURRENT_DATE"],
            'Current date MC' => ['create table t(a date default CURRENT_date)', "CURRENT_DATE"],
            'Current time LC' => ['create table t(a time default current_time)', "CURRENT_TIME"],
            'Current time UC' => ['create table t(a time default CURRENT_TIME)', "CURRENT_TIME"],
            'Current time MC' => ['create table t(a time default CURRENT_time)', "CURRENT_TIME"],
            'Current timestamp LC' => ['create table t(a datetime default current_timestamp)', "CURRENT_TIMESTAMP"],
            'Current timestamp UC' => ['create table t(a datetime default CURRENT_TIMESTAMP)', "CURRENT_TIMESTAMP"],
            'Current timestamp MC' => ['create table t(a datetime default CURRENT_timestamp)', "CURRENT_TIMESTAMP"],
            'String 1' => ['create table t(a text default \'\')', Literal::from('')],
            'String 2' => ['create table t(a text default \'value!\')', Literal::from('value!')],
            'String 3' => ['create table t(a text default \'O\'\'Brien\')', Literal::from('O\'Brien')],
            'String 4' => ['create table t(a text default \'CURRENT_TIMESTAMP\')', Literal::from('CURRENT_TIMESTAMP')],
            'String 5' => ['create table t(a text default \'current_timestamp\')', Literal::from('current_timestamp')],
            'String 6' => ['create table t(a text default \'\' /* comment */)', Literal::from('')],
            'Hexadecimal LC' => ['create table t(a integer default 0xff)', 255],
            'Hexadecimal UC' => ['create table t(a integer default 0XFF)', 255],
            'Hexadecimal MC' => ['create table t(a integer default 0x1F)', 31],
            'Integer 1' => ['create table t(a integer default 1)', 1],
            'Integer 2' => ['create table t(a integer default -1)', -1],
            'Integer 3' => ['create table t(a integer default +1)', 1],
            'Integer 4' => ['create table t(a integer default 2112)', 2112],
            'Integer 5' => ['create table t(a integer default 002112)', 2112],
            'Integer boolean 1' => ['create table t(a boolean default 1)', true],
            'Integer boolean 2' => ['create table t(a boolean default 0)', false],
            'Integer boolean 3' => ['create table t(a boolean default -1)', -1],
            'Integer boolean 4' => ['create table t(a boolean default 2)', 2],
            'Float 1' => ['create table t(a float default 1.0)', 1.0],
            'Float 2' => ['create table t(a float default +1.0)', 1.0],
            'Float 3' => ['create table t(a float default -1.0)', -1.0],
            'Float 4' => ['create table t(a float default 1.)', 1.0],
            'Float 5' => ['create table t(a float default 0.1)', 0.1],
            'Float 6' => ['create table t(a float default .1)', 0.1],
            'Float 7' => ['create table t(a float default 1e0)', 1.0],
            'Float 8' => ['create table t(a float default 1e+0)', 1.0],
            'Float 9' => ['create table t(a float default 1e+1)', 10.0],
            'Float 10' => ['create table t(a float default 1e-1)', 0.1],
            'Float 11' => ['create table t(a float default 1E-1)', 0.1],
            'Blob literal 1' => ['create table t(a float default x\'ff\')', Expression::from('x\'ff\'')],
            'Blob literal 2' => ['create table t(a float default X\'FF\')', Expression::from('X\'FF\'')],
            'Arbitrary expression' => ['create table t(a float default ((2) + (2)))', Expression::from('(2) + (2)')],
            'Pathological case 1' => ['create table t(a float default (\'/*\' || \'*/\'))', Expression::from('\'/*\' || \'*/\'')],
            'Pathological case 2' => ['create table t(a float default (\'--\' || \'stuff\'))', Expression::from('\'--\' || \'stuff\'')],
        ];
    }

    /** @dataProvider provideBooleanDefaultValues
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::parseDefaultValue
     */
    public function testGetColumnsForBooleanDefaults($tableDef, $exp)
    {
        if (!$this->adapter->databaseVersionAtLeast('3.24')) {
            $this->markTestSkipped('SQLite 3.24.0 or later is required for this test.');
        }
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $act = $this->adapter->getColumns('t')[0]->getDefault();
        if (is_object($exp)) {
            $this->assertEquals($exp, $act);
        } else {
            $this->assertSame($exp, $act);
        }
    }

    public function provideBooleanDefaultValues()
    {
        return [
            'True LC' => ['create table t(a boolean default true)', true],
            'True UC' => ['create table t(a boolean default TRUE)', true],
            'True MC' => ['create table t(a boolean default TRue)', true],
            'False LC' => ['create table t(a boolean default false)', false],
            'False UC' => ['create table t(a boolean default FALSE)', false],
            'False MC' => ['create table t(a boolean default FALse)', false],
        ];
    }

    /** @dataProvider provideTablesForTruncation
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::truncateTable
     */
    public function testTruncateTable($tableDef, $tableName, $tableId)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec($tableDef);
        $conn->exec("INSERT INTO $tableId default values");
        $conn->exec("INSERT INTO $tableId default values");
        $conn->exec("INSERT INTO $tableId default values");
        $this->assertEquals(3, $conn->query("select count(*) from $tableId")->fetchColumn(), 'Broken fixture: data were not inserted properly');
        $this->assertEquals(3, $conn->query("select max(id) from $tableId")->fetchColumn(), 'Broken fixture: data were not inserted properly');
        $this->adapter->truncateTable($tableName);
        $this->assertEquals(0, $conn->query("select count(*) from $tableId")->fetchColumn(), 'Table was not truncated');
        $conn->exec("INSERT INTO $tableId default values");
        $this->assertEquals(1, $conn->query("select max(id) from $tableId")->fetchColumn(), 'Autoincrement was not reset');
    }

    public function provideTablesForTruncation()
    {
        return [
            ['create table t(id integer primary key)', 't', 't'],
            ['create table t(id integer primary key autoincrement)', 't', 't'],
            ['create temp table t(id integer primary key)', 't', 'temp.t'],
            ['create temp table t(id integer primary key autoincrement)', 't', 'temp.t'],
            ['create table t(id integer primary key)', 'main.t', 'main.t'],
            ['create table t(id integer primary key autoincrement)', 'main.t', 'main.t'],
            ['create temp table t(id integer primary key)', 'temp.t', 'temp.t'],
            ['create temp table t(id integer primary key autoincrement)', 'temp.t', 'temp.t'],
            ['create table ["](id integer primary key)', 'main."', 'main.""""'],
            ['create table ["](id integer primary key autoincrement)', 'main."', 'main.""""'],
            ['create table [\'](id integer primary key)', 'main.\'', 'main."\'"'],
            ['create table [\'](id integer primary key autoincrement)', 'main.\'', 'main."\'"'],
            ['create table T(id integer primary key)', 't', 't'],
            ['create table T(id integer primary key autoincrement)', 't', 't'],
            ['create table t(id integer primary key)', 'T', 't'],
            ['create table t(id integer primary key autoincrement)', 'T', 't'],
        ];
    }

    public function testForeignKeyReferenceCorrectAfterRenameColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRename = 'columnToRename';
        $refTableRenamedColumn = 'renamedColumn';
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRename, 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->renameColumn($refTableColumnToRename, $refTableRenamedColumn)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertTrue($this->adapter->hasColumn($refTable->getName(), $refTableRenamedColumn));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertRegExp("/REFERENCES `{$refTable->getName()}` \(`id`\)/", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangeColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToChange = 'columnToChange';
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToChange, 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->changeColumn($refTableColumnToChange, 'text')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertEquals('text', $this->adapter->getColumns($refTable->getName())[1]->getType());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertRegExp("/REFERENCES `{$refTable->getName()}` \(`id`\)/", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterRemoveColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRemove = 'columnToRemove';
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRemove, 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->removeColumn($refTableColumnToRemove)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertFalse($this->adapter->hasColumn($refTable->getName(), $refTableColumnToRemove));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertRegExp("/REFERENCES `{$refTable->getName()}` \(`id`\)/", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangePrimaryKey()
    {
        $refTableColumnAdditionalId = 'additional_id';
        $refTableColumnId = 'ref_table_id';
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnAdditionalId, 'integer')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->changePrimaryKey($refTableColumnAdditionalId)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertTrue($this->adapter->getColumns($refTable->getName())[1]->getIdentity());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertRegExp("/REFERENCES `{$refTable->getName()}` \(`id`\)/", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterDropForeignKey()
    {
        $refTableAdditionalColumnId = 'ref_table_additional_id';
        $refTableAdditional = new \Phinx\Db\Table('ref_table_additional', [], $this->adapter);
        $refTableAdditional->save();

        $refTableColumnId = 'ref_table_id';
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableAdditionalColumnId, 'integer');
        $refTable->addForeignKey($refTableAdditionalColumnId, $refTableAdditional->getName(), 'id');
        $refTable->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable->dropForeignKey($refTableAdditionalColumnId)->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertFalse($this->adapter->hasForeignKey($refTable->getName(), [$refTableAdditionalColumnId]));

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertRegExp("/REFERENCES `{$refTable->getName()}` \(`id`\)/", $sql);
    }
}
