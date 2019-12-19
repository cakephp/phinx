<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Util\Literal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class MysqlAdapterTest extends TestCase
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

        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT
        ];
        $this->adapter = new MysqlAdapter($options, new ArrayInput([]), new NullOutput());

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
            'host' => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        ];

        try {
            $adapter = new MysqlAdapter($options, new ArrayInput([]), new NullOutput());
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

    public function testConnectionWithSocketConnection()
    {
        if (!TESTS_PHINX_DB_ADAPTER_MYSQL_UNIX_SOCKET) {
            $this->markTestSkipped('MySQL socket connection skipped. See TESTS_PHINX_DB_ADAPTER_MYSQL_UNIX_SOCKET constant.');
        }

        $options = [
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
            'unix_socket' => TESTS_PHINX_DB_ADAPTER_MYSQL_UNIX_SOCKET,
        ];

        $adapter = new MysqlAdapter($options, new ArrayInput([]), new NullOutput());
        $adapter->connect();

        $this->assertInstanceOf('\PDO', $this->adapter->getConnection());
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

    public function testCreateTableWithComment()
    {
        $tableComment = 'Table comment';
        $table = new \Phinx\Db\Table('ntable', ['comment' => $tableComment], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT table_comment FROM INFORMATION_SCHEMA.TABLES WHERE table_schema='%s' AND table_name='ntable'",
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ));
        $comment = $rows[0];

        $this->assertEquals($tableComment, $comment['table_comment'], 'Dont set table comment correctly');
    }

    public function testCreateTableWithForeignKeys()
    {
        $tag_table = new \Phinx\Db\Table('ntable_tag', [], $this->adapter);
        $tag_table->addColumn('realname', 'string')
                  ->save();

        $table = new \Phinx\Db\Table('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('tag_id', 'integer')
              ->addForeignKey('tag_id', 'ntable_tag', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
              ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT table_name, column_name, referenced_table_name, referenced_column_name
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE table_schema='%s' AND REFERENCED_TABLE_NAME='ntable_tag'",
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ));
        $foreignKey = $rows[0];

        $this->assertEquals($foreignKey['table_name'], 'ntable');
        $this->assertEquals($foreignKey['column_name'], 'tag_id');
        $this->assertEquals($foreignKey['referenced_table_name'], 'ntable_tag');
        $this->assertEquals($foreignKey['referenced_column_name'], 'id');
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
    }

    public function testCreateTableWithNoPrimaryKey()
    {
        $options = [
            'id' => false
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
            'primary_key' => ['user_id', 'tag_id']
        ];
        $table = new \Phinx\Db\Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->addColumn('tag_id', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_id']));
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

    public function testCreateTableWithFullTextIndex()
    {
        $table = new \Phinx\Db\Table('table1', ['engine' => 'MyISAM'], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['type' => 'fulltext'])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndex()
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

    public function testCreateTableWithMyISAMEngine()
    {
        $table = new \Phinx\Db\Table('ntable', ['engine' => 'MyISAM'], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $row = $this->adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'ntable'));
        $this->assertEquals('MyISAM', $row['Engine']);
    }

    public function testCreateTableAndInheritDefaultCollation()
    {
        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ];
        $adapter = new MysqlAdapter($options, new ArrayInput([]), new NullOutput());

        // Ensure the database is empty and the adapter is in a disconnected state
        $adapter->dropDatabase($options['name']);
        $adapter->createDatabase($options['name']);
        $adapter->disconnect();

        $table = new \Phinx\Db\Table('table_with_default_collation', [], $adapter);
        $table->addColumn('name', 'string')
              ->save();
        $this->assertTrue($adapter->hasTable('table_with_default_collation'));
        $row = $adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'table_with_default_collation'));
        $this->assertEquals('utf8_unicode_ci', $row['Collation']);
    }

    public function testCreateTableWithLatin1Collate()
    {
        $table = new \Phinx\Db\Table('latin1_table', ['collation' => 'latin1_general_ci'], $this->adapter);
        $table->addColumn('name', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('latin1_table'));
        $row = $this->adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'latin1_table'));
        $this->assertEquals('latin1_general_ci', $row['Collation']);
    }

    public function testCreateTableWithUnsignedPK()
    {
        $table = new \Phinx\Db\Table('ntable', ['signed' => false], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
        $column_definitions = $this->adapter->getColumns('ntable');
        foreach ($column_definitions as $column_definition) {
            if ($column_definition->getName() === 'id') {
                $this->assertFalse($column_definition->getSigned());
            }
        }
    }

    public function testCreateTableWithUnsignedNamedPK()
    {
        $table = new \Phinx\Db\Table('ntable', ['id' => 'named_id', 'signed' => false], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'named_id'));
        $column_definitions = $this->adapter->getColumns('ntable');
        foreach ($column_definitions as $column_definition) {
            if ($column_definition->getName() === 'named_id') {
                $this->assertFalse($column_definition->getSigned());
            }
        }
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));
    }

    public function testAddPrimarykey()
    {
        $table = new \Phinx\Db\Table('table1', ['id' => false], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
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
            ->addColumn('column3', 'integer')
            ->save();

        $table
            ->changePrimaryKey(['column2', 'column3'])
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
        $this->assertTrue($this->adapter->hasPrimaryKey('table1', ['column2', 'column3']));
    }

    public function testDropPrimaryKey()
    {
        $table = new \Phinx\Db\Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testAddComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment1')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT table_comment
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE table_schema='%s'
                        AND table_name='%s'",
                TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
                'table1'
            )
        );
        $this->assertEquals('comment1', $rows[0]['table_comment']);
    }

    public function testChangeComment()
    {
        $table = new \Phinx\Db\Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment2')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT table_comment
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE table_schema='%s'
                        AND table_name='%s'",
                TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
                'table1'
            )
        );
        $this->assertEquals('comment2', $rows[0]['table_comment']);
    }

    public function testDropComment()
    {
        $table = new \Phinx\Db\Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment(null)
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT table_comment
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE table_schema='%s'
                        AND table_name='%s'",
                TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
                'table1'
            )
        );
        $this->assertEquals('', $rows[0]['table_comment']);
    }

    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));

        $table->rename('table2')->save();
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
        $table->addColumn('realname', 'string', ['after' => 'id'])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals("test", $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals("0", $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultBoolean()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_true', 'boolean', ['default' => true])
              ->addColumn('default_false', 'boolean', ['default' => false])
              ->addColumn('default_null', 'boolean', ['default' => null, 'null' => true])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('1', $rows[1]['Default']);
        $this->assertEquals('0', $rows[2]['Default']);
        $this->assertNull($rows[3]['Default']);
    }

    public function testAddColumnWithDefaultLiteral()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_ts', 'timestamp', ['default' => Literal::from('CURRENT_TIMESTAMP')])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        // MariaDB returns current_timestamp()
        $this->assertTrue('CURRENT_TIMESTAMP' === $rows[1]['Default'] || 'current_timestamp()' === $rows[1]['Default']);
    }

    public function testAddIntegerColumnWithDefaultSigned()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'integer')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('int(11)', $rows[1]['Type']);
    }

    public function testAddIntegerColumnWithSignedEqualsFalse()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('int(11) unsigned', $rows[1]['Type']);
    }

    public function testAddSmallIntegerColumnWithDefaultSigned()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'smallinteger')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('smallint(6)', $rows[1]['Type']);
    }

    public function testAddSmallIntegerColumnWithSignedEqualsFalse()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'smallinteger', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('smallint(6) unsigned', $rows[1]['Type']);
    }

    public function testAddBigIntegerColumnWithDefaultSigned()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'biginteger')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('bigint(20)', $rows[1]['Type']);
    }

    public function testAddBigIntegerColumnWithSignedEqualsFalse()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'biginteger', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('bigint(20) unsigned', $rows[1]['Type']);
    }

    public function testAddDoubleColumnWithDefaultSigned()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('foo', 'double')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('double', $rows[1]['Type']);
    }

    public function testAddDoubleColumnWithSignedEqualsFalse()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('foo', 'double', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('double unsigned', $rows[1]['Type']);
    }

    public function testAddBooleanColumnWithSignedEqualsFalse()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('test_boolean'));
        $table->addColumn('test_boolean', 'boolean', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('tinyint(1) unsigned', $rows[1]['Type']);
    }

    public function testAddStringColumnWithSignedEqualsFalse()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'string', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('varchar(255)', $rows[1]['Type']);
    }

    public function testAddStringColumnWithCustomCollation()
    {
        $table = new \Phinx\Db\Table('table_custom_collation', ['collation' => 'utf8_general_ci'], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('string_collation_default'));
        $this->assertFalse($table->hasColumn('string_collation_custom'));
        $table->addColumn('string_collation_default', 'string', [])->save();
        $table->addColumn('string_collation_custom', 'string', ['collation' => 'utf8mb4_unicode_ci'])->save();
        $rows = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM table_custom_collation');
        $this->assertEquals('utf8_general_ci', $rows[1]['Collation']);
        $this->assertEquals('utf8mb4_unicode_ci', $rows[2]['Collation']);
    }

    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));

        $table->renameColumn('column1', 'column2')->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenameColumnPreserveComment()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => 'comment1'])
              ->save();

        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));
        $columns = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM t');
        $this->assertEquals('comment1', $columns[1]['Comment']);

        $table->renameColumn('column1', 'column2')->save();

        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
        $columns = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM t');
        $this->assertEquals('comment1', $columns[1]['Comment']);
    }

    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        try {
            $table->renameColumn('column2', 'column1')->save();
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
        $table->changeColumn('column1', 'string')->save();
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
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals("test1", $rows[1]['Default']);
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
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals("0", $rows[1]['Default']);
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
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNull($rows[1]['Default']);
    }

    public function testLongTextColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('longtext', $sqlType['name']);
    }

    public function testMediumTextColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('mediumtext', $sqlType['name']);
    }

    public function testTinyTextColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text', ['limit' => MysqlAdapter::TEXT_TINY])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('tinytext', $sqlType['name']);
    }

    public function testBigIntegerColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_BIG])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('bigint', $sqlType['name']);
    }

    public function testMediumIntegerColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('mediumint', $sqlType['name']);
    }

    public function testSmallIntegerColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('smallint', $sqlType['name']);
    }

    public function testTinyIntegerColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_TINY])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('tinyint', $sqlType['name']);
    }

    public function testIntegerColumnLimit()
    {
        $limit = 8;
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    public function testDatetimeColumn()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'datetime')->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals(null, $sqlType['limit']);
    }

    public function testDatetimeColumnLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $limit = 6;
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'datetime', ['limit' => $limit])->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    public function testTimeColumnLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $limit = 3;
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'time', ['limit' => $limit])->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    public function testTimestampColumnLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $limit = 1;
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'timestamp', ['limit' => $limit])->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    /**
     * @expectedException \PDOException
     */
    public function testTimestampInvalidLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'timestamp', ['limit' => 7])->save();
    }

    public function testDropColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $table->removeColumn('column1')->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
    }

    public function columnsProvider()
    {
        return [
            ['column1', 'string', []],
            ['column2', 'smallinteger', []],
            ['column3', 'integer', []],
            ['column4', 'biginteger', []],
            ['column5', 'text', []],
            ['column6', 'float', []],
            ['column7', 'decimal', []],
            ['decimal_precision_scale', 'decimal', ['precision' => 10, 'scale' => 2]],
            ['decimal_limit', 'decimal', ['limit' => 10]],
            ['decimal_precision', 'decimal', ['precision' => 10]],
            ['column8', 'datetime', []],
            ['column9', 'time', []],
            ['column10', 'timestamp', []],
            ['column11', 'date', []],
            ['column12', 'binary', []],
            ['column13', 'boolean', []],
            ['column14', 'string', ['limit' => 10]],
            ['column15', 'integer', ['limit' => 10]],
            ['column16', 'geometry', []],
            ['column17', 'point', []],
            ['column18', 'linestring', []],
            ['column19', 'polygon', []],
            ['column20', 'uuid', []],
            ['column21', 'set', ['values' => ['one', 'two']]],
            ['column22', 'enum', ['values' => ['three', 'four']]],
            ['column23', 'bit', []]
        ];
    }

    /**
     *
     * @dataProvider columnsProvider
     */
    public function testGetColumns($colName, $type, $options)
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());
        $this->assertEquals($type, $columns[1]->getType());

        if (isset($options['limit'])) {
            $this->assertEquals($options['limit'], $columns[1]->getLimit());
        }

        if (isset($options['values'])) {
            $this->assertEquals($options['values'], $columns[1]->getValues());
        }

        if (isset($options['precision'])) {
            $this->assertEquals($options['precision'], $columns[1]->getPrecision());
        }

        if (isset($options['scale'])) {
            $this->assertEquals($options['scale'], $columns[1]->getScale());
        }
    }

    public function testDescribeTable()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string');
        $table->save();

        $described = $this->adapter->describeTable('t');

        $this->assertContains($described['TABLE_TYPE'], ['VIEW', 'BASE TABLE']);
        $this->assertEquals($described['TABLE_NAME'], 't');
        $this->assertEquals($described['TABLE_SCHEMA'], TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $this->assertEquals($described['TABLE_ROWS'], 0);
    }

    public function testGetColumnsReservedTableName()
    {
        $table = new \Phinx\Db\Table('group', [], $this->adapter);
        $table->addColumn('column1', 'string')->save();
        $columns = $this->adapter->getColumns('group');
        $this->assertCount(2, $columns);
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

    public function testAddMultipleFulltextIndex()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->addColumn('bio', 'text')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $this->assertFalse($table->hasIndex('username'));
        $this->assertFalse($table->hasIndex('address'));
        $table->addIndex('email')
              ->addIndex('username', ['type' => 'fulltext'])
              ->addIndex('bio', ['type' => 'fulltext'])
              ->addIndex(['email', 'bio'], ['type' => 'fulltext'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->assertTrue($table->hasIndex('username'));
        $this->assertTrue($table->hasIndex('bio'));
        $this->assertTrue($table->hasIndex(['email', 'bio']));
    }

    public function testAddIndexWithLimit()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['limit' => 50])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email"',
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ))->fetch(\PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 50);
    }

    public function testAddMultiIndexesWithLimitSpecifier()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndex(['email', 'username']));
        $table->addIndex(['email', 'username'], ['limit' => [ 'email' => 3, 'username' => 2 ]])
              ->save();
        $this->assertTrue($table->hasIndex(['email', 'username']));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "email"',
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ))->fetch(\PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 3);
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "username"',
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ))->fetch(\PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 2);
    }

    public function testAddSingleIndexesWithLimitSpecifier()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addColumn('username', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['limit' => [ 'email' => 3, 2 ]])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "email"',
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ))->fetch(\PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 3);
    }

    public function testDropIndex()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $table->removeIndex(['email'])->save();
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $table2->removeIndex(['fname', 'lname'])->save();
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // index with name specified, but dropping it by column name
        $table3 = new \Phinx\Db\Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'someindexname'])
              ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $table3->removeIndex(['email'])->save();
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('table4', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $table4->removeIndex(['fname', 'lname'])->save();
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));

        // don't drop multiple column index when dropping single column
        $table2 = new \Phinx\Db\Table('table5', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));

        try {
            $table2->removeIndex(['fname'])->save();
        } catch (\InvalidArgumentException $e) {
        }
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));

        // don't drop multiple column index with name specified when dropping
        // single column
        $table4 = new \Phinx\Db\Table('table6', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));

        try {
            $table4->removeIndex(['fname'])->save();
        } catch (\InvalidArgumentException $e) {
        }

        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $table->removeIndexByName('myemailindex')->save();
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'twocolumnindex'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $table2->removeIndexByName('twocolumnindex')->save();
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

    public function testAddForeignKeyForTableWithUnsignedPK()
    {
        $refTable = new \Phinx\Db\Table('ref_table', ['signed' => false], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyForTableWithUnsignedPK()
    {
        $refTable = new \Phinx\Db\Table('ref_table', ['signed' => false], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyAsString()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey('ref_table_id')->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testHasForeignKeyAsString()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), 'ref_table_id'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), 'ref_table_id2'));
    }

    public function testHasForeignKeyWithConstraint()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));
    }

    public function testHasForeignKeyWithConstraintForTableWithUnsignedPK()
    {
        $refTable = new \Phinx\Db\Table('ref_table', ['signed' => false], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE));
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

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT column_name, column_comment FROM information_schema.columns WHERE table_schema='%s' AND table_name='table1'",
            TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE
        ));
        $columnWithComment = $rows[1];

        $this->assertEquals($comment, $columnWithComment['column_comment'], 'Dont set column comment correctly');
    }

    public function testAddGeoSpatialColumns()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('geo_geom'));
        $table->addColumn('geo_geom', 'geometry')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('geometry', $rows[1]['Type']);
    }

    public function testAddSetColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('set_column'));
        $table->addColumn('set_column', 'set', ['values' => ['one', 'two']])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals("set('one','two')", $rows[1]['Type']);
    }

    public function testAddEnumColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('enum_column'));
        $table->addColumn('enum_column', 'enum', ['values' => ['one', 'two']])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals("enum('one','two')", $rows[1]['Type']);
    }

    public function testEnumColumnValuesFilledUpFromSchema()
    {
        // Creating column with values
        (new \Phinx\Db\Table('table1', [], $this->adapter))
            ->addColumn('enum_column', 'enum', ['values' => ['one', 'two']])
            ->save();

        // Reading them back
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $columns = $table->getColumns();
        $enumColumn = end($columns);
        $this->assertEquals(AdapterInterface::PHINX_TYPE_ENUM, $enumColumn->getType());
        $this->assertEquals(['one', 'two'], $enumColumn->getValues());
    }

    public function testHasColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $this->assertFalse($table->hasColumn('column2'));
        $this->assertTrue($table->hasColumn('column1'));
    }

    public function testHasColumnReservedName()
    {
        $tableQuoted = new \Phinx\Db\Table('group', [], $this->adapter);
        $tableQuoted->addColumn('value', 'string')
                    ->save();

        $this->assertFalse($tableQuoted->hasColumn('column2'));
        $this->assertTrue($tableQuoted->hasColumn('value'));
    }

    public function testBulkInsertData()
    {
        $data = [
            [
                'column1' => 'value1',
                'column2' => 1,
            ],
            [
                'column1' => 'value2',
                'column2' => 2,
            ],
            [
                'column1' => 'value3',
                'column2' => 3,
            ]
        ];
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals('test', $rows[0]['column3']);
        $this->assertEquals('test', $rows[2]['column3']);
    }

    public function testInsertData()
    {
        $data = [
            [
                'column1' => 'value1',
                'column2' => 1,
            ],
            [
                'column1' => 'value2',
                'column2' => 2,
            ],
            [
                'column1' => 'value3',
                'column2' => 3,
                'column3' => 'foo',
            ]
        ];
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals('test', $rows[0]['column3']);
        $this->assertEquals('foo', $rows[2]['column3']);
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
CREATE TABLE `table1` (`id` INT(11) NOT NULL AUTO_INCREMENT, `column1` VARCHAR(255) NOT NULL, `column2` INT(11) NOT NULL, `column3` VARCHAR(255) NOT NULL DEFAULT 'test', PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
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
            'string_col' => 'test data'
        ]);

        $this->adapter->insert($table->getTable(), [
            'string_col' => null
        ]);

        $this->adapter->insert($table->getTable(), [
            'int_col' => 23
        ]);

        $expectedOutput = <<<'OUTPUT'
INSERT INTO `table1` (`string_col`) VALUES ('test data');
INSERT INTO `table1` (`string_col`) VALUES (null);
INSERT INTO `table1` (`int_col`) VALUES (23);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
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

        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`column1` VARCHAR(255) NOT NULL, `column2` INT(11) NOT NULL, PRIMARY KEY (`column1`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
INSERT INTO `table1` (`column1`, `column2`) VALUES ('id1', 1);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
    }

    public function testDumpTransaction()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $this->adapter->beginTransaction();
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();
        $this->adapter->commitTransaction();
        $this->adapter->rollbackTransaction();

        $actualOutput = $consoleOutput->fetch();
        $this->assertStringStartsWith("START TRANSACTION;\n", $actualOutput, 'Passing the --dry-run doesn\'t dump the transaction to the output');
        $this->assertStringEndsWith("COMMIT;\nROLLBACK;\n", $actualOutput, 'Passing the --dry-run doesn\'t dump the transaction to the output');
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

    public function testLiteralSupport()
    {
        $createQuery = <<<'INPUT'
CREATE TABLE `test` (`double_col` double NOT NULL)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new \Phinx\Db\Table('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('double'), array_pop($columns)->getType());
    }
}
