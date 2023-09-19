<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Adapter;

use Cake\Database\Query;
use InvalidArgumentException;
use PDO;
use PDOException;
use Phinx\Config\FeatureFlags;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Util\Literal;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use UnexpectedValueException;

class MysqlAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\MysqlAdapter
     */
    private $adapter;

    protected function setUp(): void
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }

        $this->adapter = new MysqlAdapter(MYSQL_DB_CONFIG, new ArrayInput([]), new NullOutput());

        // ensure the database is empty for each test
        $this->adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $this->adapter->createDatabase(MYSQL_DB_CONFIG['name']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
    }

    private function usingMysql8(): bool
    {
        return version_compare($this->adapter->getAttribute(PDO::ATTR_SERVER_VERSION), '8.0.0', '>=');
    }

    public function testConnection()
    {
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $this->adapter->getConnection()->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testConnectionWithFetchMode()
    {
        $options = $this->adapter->getOptions();
        $options['fetch_mode'] = 'assoc';
        $this->adapter->setOptions($options);
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
        $this->assertSame(PDO::FETCH_ASSOC, $this->adapter->getConnection()->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
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
        $options = ['user' => 'invalid', 'pass' => 'invalid'] + MYSQL_DB_CONFIG;

        try {
            $adapter = new MysqlAdapter($options, new ArrayInput([]), new NullOutput());
            $adapter->connect();
            $this->fail('Expected the adapter to throw an exception');
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertStringContainsString('There was a problem connecting to the database', $e->getMessage());
        }
    }

    public function testConnectionWithSocketConnection()
    {
        if (!getenv('MYSQL_UNIX_SOCKET')) {
            $this->markTestSkipped('MySQL socket connection skipped.');
        }

        $options = ['unix_socket' => getenv('MYSQL_UNIX_SOCKET')] + MYSQL_DB_CONFIG;
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
        new Table($this->adapter->getSchemaTableName(), [], $this->adapter);
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

    public function testHasTableUnderstandsSchemaNotation()
    {
        $this->assertTrue($this->adapter->hasTable('performance_schema.threads'), 'Failed asserting hasTable understands tables in another schema.');
        $this->assertFalse($this->adapter->hasTable('performance_schema.unknown_table'));
        $this->assertFalse($this->adapter->hasTable('unknown_schema.phinxlog'));
    }

    public function testHasTableRespectsDotInTableName()
    {
        $sql = "CREATE TABLE `discouraged.naming.convention`
                (id INT(11) NOT NULL)
                ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $this->adapter->execute($sql);
        $this->assertTrue($this->adapter->hasTable('discouraged.naming.convention'));
    }

    public function testCreateTable()
    {
        $table = new Table('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('email', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $columns = $this->adapter->getColumns('ntable');
        $this->assertCount(3, $columns);
        $this->assertSame('id', $columns[0]->getName());
        $this->assertFalse($columns[0]->isSigned());
    }

    public function testCreateTableWithComment()
    {
        $tableComment = 'Table comment';
        $table = new Table('ntable', ['comment' => $tableComment], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='ntable'",
            MYSQL_DB_CONFIG['name']
        ));
        $comment = $rows[0];

        $this->assertEquals($tableComment, $comment['TABLE_COMMENT'], 'Dont set table comment correctly');
    }

    public function testCreateTableWithForeignKeys()
    {
        $tag_table = new Table('ntable_tag', [], $this->adapter);
        $tag_table->addColumn('realname', 'string')
                  ->save();

        $table = new Table('ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
              ->addColumn('tag_id', 'integer', ['signed' => false])
              ->addForeignKey('tag_id', 'ntable_tag', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
              ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'realname'));
        $this->assertFalse($this->adapter->hasColumn('ntable', 'address'));

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA='%s' AND REFERENCED_TABLE_NAME='ntable_tag'",
            MYSQL_DB_CONFIG['name']
        ));
        $foreignKey = $rows[0];

        $this->assertEquals($foreignKey['TABLE_NAME'], 'ntable');
        $this->assertEquals($foreignKey['COLUMN_NAME'], 'tag_id');
        $this->assertEquals($foreignKey['REFERENCED_TABLE_NAME'], 'ntable_tag');
        $this->assertEquals($foreignKey['REFERENCED_COLUMN_NAME'], 'id');
    }

    public function testCreateTableCustomIdColumn()
    {
        $table = new Table('ntable', ['id' => 'custom_id'], $this->adapter);
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
            'id' => false,
        ];
        $table = new Table('atable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->save();
        $this->assertFalse($this->adapter->hasColumn('atable', 'id'));
    }

    public function testCreateTableWithConflictingPrimaryKeys()
    {
        $options = [
            'primary_key' => 'user_id',
        ];
        $table = new Table('atable', $options, $this->adapter);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot enable an auto incrementing ID field and a primary key');
        $table->addColumn('user_id', 'integer')->save();
    }

    public function testCreateTableWithPrimaryKeySetToImplicitId()
    {
        $options = [
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithPrimaryKeyArraySetToImplicitId()
    {
        $options = [
            'primary_key' => ['id'],
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithMultiplePrimaryKeyArraySetToImplicitId()
    {
        $options = [
            'primary_key' => ['id', 'user_id'],
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot enable an auto incrementing ID field and a primary key');
        $table->addColumn('user_id', 'integer')->save();
    }

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('tag_id', 'integer', ['null' => false])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsUuid()
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'uuid', ['null' => false])->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    /**
     * @return void
     */
    public function testCreateTableWithPrimaryKeyAsBinaryUuid()
    {
        $options = [
            'id' => false,
            'primary_key' => 'id',
        ];
        $table = new Table('ztable', $options, $this->adapter);
        $table->addColumn('id', 'binaryuuid', ['null' => false])->save();
        $table->addColumn('user_id', 'integer')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('ztable', 'user_id'));
    }

    public function testCreateTableWithMultipleIndexes()
    {
        $table = new Table('table1', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string', ['limit' => 191])
              ->addIndex('email', ['unique' => true])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithFullTextIndex()
    {
        $table = new Table('table1', ['engine' => 'MyISAM'], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['type' => 'fulltext'])
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndex()
    {
        $table = new Table('table1', [], $this->adapter);
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
        $table = new Table('ntable', ['engine' => 'MyISAM'], $this->adapter);
        $table->addColumn('realname', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $row = $this->adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'ntable'));
        $this->assertEquals('MyISAM', $row['Engine']);
    }

    public function testCreateTableAndInheritDefaultCollation()
    {
        $options = MYSQL_DB_CONFIG + [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
        $adapter = new MysqlAdapter($options, new ArrayInput([]), new NullOutput());

        // Ensure the database is empty and the adapter is in a disconnected state
        $adapter->dropDatabase($options['name']);
        $adapter->createDatabase($options['name']);
        $adapter->disconnect();

        $table = new Table('table_with_default_collation', [], $adapter);
        $table->addColumn('name', 'string')
              ->save();
        $this->assertTrue($adapter->hasTable('table_with_default_collation'));
        $row = $adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'table_with_default_collation'));
        $this->assertEquals('utf8mb4_unicode_ci', $row['Collation']);
    }

    public function testCreateTableWithLatin1Collate()
    {
        $table = new Table('latin1_table', ['collation' => 'latin1_general_ci'], $this->adapter);
        $table->addColumn('name', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasTable('latin1_table'));
        $row = $this->adapter->fetchRow(sprintf("SHOW TABLE STATUS WHERE Name = '%s'", 'latin1_table'));
        $this->assertEquals('latin1_general_ci', $row['Collation']);
    }

    public function testCreateTableWithSignedPK()
    {
        $table = new Table('ntable', ['signed' => true], $this->adapter);
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
                $this->assertTrue($column_definition->getSigned());
            }
        }
    }

    public function testCreateTableWithUnsignedPK()
    {
        $table = new Table('ntable', ['signed' => false], $this->adapter);
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
        $table = new Table('ntable', ['id' => 'named_id', 'signed' => false], $this->adapter);
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

    /**
     * @runInSeparateProcess
     */
    public function testUnsignedPksFeatureFlag()
    {
        $this->adapter->connect();

        FeatureFlags::$unsignedPrimaryKeys = false;

        $table = new Table('table1', [], $this->adapter);
        $table->create();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(1, $columns);
        $this->assertSame('id', $columns[0]->getName());
        $this->assertTrue($columns[0]->getSigned());
    }

    public function testCreateTableWithLimitPK()
    {
        $table = new Table('ntable', ['id' => 'id', 'limit' => 4], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'id'));
        $column_definitions = $this->adapter->getColumns('ntable');
        $this->assertSame($this->usingMysql8() ? null : 4, $column_definitions[0]->getLimit());
    }

    public function testCreateTableWithSchema()
    {
        $table = new Table(MYSQL_DB_CONFIG['name'] . '.ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('ntable'));
    }

    public function testAddPrimarykey()
    {
        $table = new Table('table1', ['id' => false], $this->adapter);
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
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
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
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer', ['null' => false])
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testAddComment()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment1')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT TABLE_COMMENT
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='%s'
                        AND TABLE_NAME='%s'",
                MYSQL_DB_CONFIG['name'],
                'table1'
            )
        );
        $this->assertEquals('comment1', $rows[0]['TABLE_COMMENT']);
    }

    public function testChangeComment()
    {
        $table = new Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment('comment2')
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT TABLE_COMMENT
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='%s'
                        AND TABLE_NAME='%s'",
                MYSQL_DB_CONFIG['name'],
                'table1'
            )
        );
        $this->assertEquals('comment2', $rows[0]['TABLE_COMMENT']);
    }

    public function testDropComment()
    {
        $table = new Table('table1', ['comment' => 'comment1'], $this->adapter);
        $table->save();

        $table
            ->changeComment(null)
            ->save();

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT TABLE_COMMENT
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='%s'
                        AND TABLE_NAME='%s'",
                MYSQL_DB_CONFIG['name'],
                'table1'
            )
        );
        $this->assertEquals('', $rows[0]['TABLE_COMMENT']);
    }

    public function testRenameTable()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));

        $table->rename('table2')->save();
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testAddColumn()
    {
        $table = new Table('table1', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('test', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals('0', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('', $rows[1]['Default']);
    }

    public function testAddColumnWithDefaultBoolean()
    {
        $table = new Table('table1', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_ts', 'timestamp', ['default' => Literal::from('CURRENT_TIMESTAMP')])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        // MariaDB returns current_timestamp()
        $this->assertTrue($rows[1]['Default'] === 'CURRENT_TIMESTAMP' || $rows[1]['Default'] === 'current_timestamp()');
    }

    public function testAddColumnWithCustomType()
    {
        $this->adapter->setDataDomain([
            'custom1' => [
                'type' => 'enum',
                'null' => true,
                'values' => 'a,b,c',
            ],
            'custom2' => [
                'type' => 'enum',
                'null' => true,
                'values' => ['a', 'b', 'c'],
            ],
        ]);

        (new Table('table1', [], $this->adapter))
            ->addColumn('custom1', 'custom1')
            ->addColumn('custom2', 'custom2')
            ->addColumn('custom_ext', 'custom2', [
                'null' => false,
                'values' => ['d', 'e', 'f'],
            ])
            ->save();

        $this->assertTrue($this->adapter->hasTable('table1'));

        $columns = $this->adapter->getColumns('table1');

        $this->assertArrayHasKey(1, $columns);
        $this->assertArrayHasKey(2, $columns);
        $this->assertArrayHasKey(3, $columns);

        foreach ([1, 2] as $index) {
            $column = $this->adapter->getColumns('table1')[$index];
            $this->assertSame("custom{$index}", $column->getName());
            $this->assertSame('enum', $column->getType());
            $this->assertSame(['a', 'b', 'c'], $column->getValues());
            $this->assertTrue($column->getNull());
        }

        $column = $this->adapter->getColumns('table1')[3];
        $this->assertSame('custom_ext', $column->getName());
        $this->assertSame('enum', $column->getType());
        $this->assertSame(['d', 'e', 'f'], $column->getValues());
        $this->assertFalse($column->getNull());
    }

    public function testAddColumnFirst()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('new_id', 'integer', ['after' => MysqlAdapter::FIRST])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertSame('new_id', $rows[0]['Field']);
    }

    public function integerDataProvider()
    {
        return [
            ['integer', [], 'int', '11', ''],
            ['integer', ['signed' => false], 'int', '11', ' unsigned'],
            ['integer', ['limit' => 8], 'int', '8', ''],
            ['smallinteger', [], 'smallint', '6', ''],
            ['smallinteger', ['signed' => false], 'smallint', '6', ' unsigned'],
            ['smallinteger', ['limit' => 3], 'smallint', '3', ''],
            ['biginteger', [], 'bigint', '20', ''],
            ['biginteger', ['signed' => false], 'bigint', '20', ' unsigned'],
            ['biginteger', ['limit' => 12], 'bigint', '12', ''],
        ];
    }

    /**
     * @dataProvider integerDataProvider
     */
    public function testIntegerColumnTypes($phinx_type, $options, $sql_type, $width, $extra)
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', $phinx_type, $options)
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');

        $type = $sql_type;
        if (!$this->usingMysql8()) {
            $type .= '(' . $width . ')';
        }
        $type .= $extra;
        $this->assertEquals($type, $rows[1]['Type']);
    }

    public function testAddDoubleColumnWithDefaultSigned()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('foo', 'double')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('double', $rows[1]['Type']);
    }

    public function testAddDoubleColumnWithSignedEqualsFalse()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('foo', 'double', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('double unsigned', $rows[1]['Type']);
    }

    public function testAddBooleanColumnWithSignedEqualsFalse()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('test_boolean'));
        $table->addColumn('test_boolean', 'boolean', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');

        $type = $this->usingMysql8() ? 'tinyint' : 'tinyint(1)';
        $this->assertEquals($type . ' unsigned', $rows[1]['Type']);
    }

    public function testAddStringColumnWithSignedEqualsFalse()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('user_id'));
        $table->addColumn('user_id', 'string', ['signed' => false])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('varchar(255)', $rows[1]['Type']);
    }

    public function testAddStringColumnWithCustomCollation()
    {
        $table = new Table('table_custom_collation', ['collation' => 'utf8mb4_unicode_ci'], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('string_collation_default'));
        $this->assertFalse($table->hasColumn('string_collation_custom'));
        $table->addColumn('string_collation_default', 'string', [])->save();
        $table->addColumn('string_collation_custom', 'string', ['collation' => 'utf8mb4_unicode_ci'])->save();
        $rows = $this->adapter->fetchAll('SHOW FULL COLUMNS FROM table_custom_collation');
        $this->assertEquals('utf8mb4_unicode_ci', $rows[1]['Collation']);
        $this->assertEquals('utf8mb4_unicode_ci', $rows[2]['Collation']);
    }

    public function testRenameColumn()
    {
        $table = new Table('t', [], $this->adapter);
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
        $table = new Table('t', [], $this->adapter);
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
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        try {
            $table->renameColumn('column2', 'column1')->save();
            $this->fail('Expected the adapter to throw an exception');
        } catch (InvalidArgumentException $e) {
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
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $table->changeColumn('column1', 'string')->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));

        $newColumn2 = new Column();
        $newColumn2->setName('column2')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn2)->save();
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testChangeColumnDefaultValue()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
              ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault('test1')
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals('test1', $rows[1]['Default']);
    }

    public function testChangeColumnDefaultToZero()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
              ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault(0)
                   ->setType('integer');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNotNull($rows[1]['Default']);
        $this->assertEquals('0', $rows[1]['Default']);
    }

    public function testChangeColumnDefaultToNull()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
              ->save();
        $newColumn1 = new Column();
        $newColumn1->setDefault(null)
                   ->setType('string');
        $table->changeColumn('column1', $newColumn1)->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM t');
        $this->assertNull($rows[1]['Default']);
    }

    public function sqlTypeIntConversionProvider()
    {
        return [
          // tinyint
          [AdapterInterface::PHINX_TYPE_TINY_INTEGER, null, 'tinyint', 4],
          [AdapterInterface::PHINX_TYPE_TINY_INTEGER, 2, 'tinyint', 2],
          [AdapterInterface::PHINX_TYPE_TINY_INTEGER, MysqlAdapter::INT_TINY, 'tinyint', 4],
          // smallint
          [AdapterInterface::PHINX_TYPE_SMALL_INTEGER, null, 'smallint', 6],
          [AdapterInterface::PHINX_TYPE_SMALL_INTEGER, 3, 'smallint', 3],
          [AdapterInterface::PHINX_TYPE_SMALL_INTEGER, MysqlAdapter::INT_SMALL, 'smallint', 6],
          // medium
          [AdapterInterface::PHINX_TYPE_MEDIUM_INTEGER, null, 'mediumint', 8],
          [AdapterInterface::PHINX_TYPE_MEDIUM_INTEGER, 2, 'mediumint', 2],
          [AdapterInterface::PHINX_TYPE_MEDIUM_INTEGER, MysqlAdapter::INT_MEDIUM, 'mediumint', 8],
          // integer
          [AdapterInterface::PHINX_TYPE_INTEGER, null, 'int', 11],
          [AdapterInterface::PHINX_TYPE_INTEGER, 4, 'int', 4],
          [AdapterInterface::PHINX_TYPE_INTEGER, MysqlAdapter::INT_TINY, 'tinyint', 4],
          [AdapterInterface::PHINX_TYPE_INTEGER, MysqlAdapter::INT_SMALL, 'smallint', 6],
          [AdapterInterface::PHINX_TYPE_INTEGER, MysqlAdapter::INT_MEDIUM, 'mediumint', 8],
          [AdapterInterface::PHINX_TYPE_INTEGER, MysqlAdapter::INT_REGULAR, 'int', 11],
          [AdapterInterface::PHINX_TYPE_INTEGER, MysqlAdapter::INT_BIG, 'bigint', 20],
          // bigint
          [AdapterInterface::PHINX_TYPE_BIG_INTEGER, null, 'bigint', 20],
          [AdapterInterface::PHINX_TYPE_BIG_INTEGER, 4, 'bigint', 4],
          [AdapterInterface::PHINX_TYPE_BIG_INTEGER, MysqlAdapter::INT_BIG, 'bigint', 20],
        ];
    }

    /**
     * @dataProvider sqlTypeIntConversionProvider
     * The second argument is not typed as MysqlAdapter::INT_BIG is a float, and all other values are integers
     */
    public function testGetSqlTypeIntegerConversion(string $type, $limit, string $expectedType, int $expectedLimit)
    {
        $sqlType = $this->adapter->getSqlType($type, $limit);
        $this->assertSame($expectedType, $sqlType['name']);
        $this->assertSame($expectedLimit, $sqlType['limit']);
    }

    public function testLongTextColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('longtext', $sqlType['name']);
    }

    public function testMediumTextColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('mediumtext', $sqlType['name']);
    }

    public function testTinyTextColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'text', ['limit' => MysqlAdapter::TEXT_TINY])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('tinytext', $sqlType['name']);
    }

    public function binaryToBlobAutomaticConversionData()
    {
        return [
          [null, 'binary', 255],
          [64, 'binary', 64],
          [MysqlAdapter::BLOB_REGULAR - 20, 'blob', MysqlAdapter::BLOB_REGULAR],
          [MysqlAdapter::BLOB_REGULAR, 'blob', MysqlAdapter::BLOB_REGULAR],
          [MysqlAdapter::BLOB_REGULAR + 20, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
          [MysqlAdapter::BLOB_MEDIUM, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
          [MysqlAdapter::BLOB_MEDIUM + 20, 'longblob', MysqlAdapter::BLOB_LONG],
          [MysqlAdapter::BLOB_LONG, 'longblob', MysqlAdapter::BLOB_LONG],
          [MysqlAdapter::BLOB_LONG + 20, 'longblob', MysqlAdapter::BLOB_LONG],
        ];
    }

    /** @dataProvider binaryToBlobAutomaticConversionData */
    public function testBinaryToBlobAutomaticConversion(?int $limit, string $expectedType, int $expectedLimit)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'binary', ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertSame($expectedType, $sqlType['name']);
        $this->assertSame($expectedLimit, $columns[1]->getLimit());
    }

    public function varbinaryToBlobAutomaticConversionData()
    {
        return [
          [null, 'varbinary', 255],
          [64, 'varbinary', 64],
          [MysqlAdapter::BLOB_REGULAR - 20, 'blob', MysqlAdapter::BLOB_REGULAR],
          [MysqlAdapter::BLOB_REGULAR, 'blob', MysqlAdapter::BLOB_REGULAR],
          [MysqlAdapter::BLOB_REGULAR + 20, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
          [MysqlAdapter::BLOB_MEDIUM, 'mediumblob', MysqlAdapter::BLOB_MEDIUM],
          [MysqlAdapter::BLOB_MEDIUM + 20, 'longblob', MysqlAdapter::BLOB_LONG],
          [MysqlAdapter::BLOB_LONG, 'longblob', MysqlAdapter::BLOB_LONG],
          [MysqlAdapter::BLOB_LONG + 20, 'longblob', MysqlAdapter::BLOB_LONG],
        ];
    }

    /** @dataProvider varbinaryToBlobAutomaticConversionData */
    public function testVarbinaryToBlobAutomaticConversion(?int $limit, string $expectedType, int $expectedLimit)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'varbinary', ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertSame($expectedType, $sqlType['name']);
        $this->assertSame($expectedLimit, $columns[1]->getLimit());
    }

    public function blobColumnsData()
    {
        return [
          // Tiny blobs
          ['tinyblob', 'tinyblob', null, MysqlAdapter::BLOB_TINY],
          ['tinyblob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['tinyblob', 'blob', MysqlAdapter::BLOB_TINY + 20, MysqlAdapter::BLOB_REGULAR],
          ['tinyblob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['tinyblob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
          // Regular blobs
          ['blob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['blob', 'blob', null, MysqlAdapter::BLOB_REGULAR],
          ['blob', 'blob', MysqlAdapter::BLOB_REGULAR, MysqlAdapter::BLOB_REGULAR],
          ['blob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['blob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
          // medium blobs
          ['mediumblob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['mediumblob', 'blob', MysqlAdapter::BLOB_REGULAR, MysqlAdapter::BLOB_REGULAR],
          ['mediumblob', 'mediumblob', null, MysqlAdapter::BLOB_MEDIUM],
          ['mediumblob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['mediumblob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
          // long blobs
          ['longblob', 'tinyblob', MysqlAdapter::BLOB_TINY, MysqlAdapter::BLOB_TINY],
          ['longblob', 'blob', MysqlAdapter::BLOB_REGULAR, MysqlAdapter::BLOB_REGULAR],
          ['longblob', 'mediumblob', MysqlAdapter::BLOB_MEDIUM, MysqlAdapter::BLOB_MEDIUM],
          ['longblob', 'longblob', null, MysqlAdapter::BLOB_LONG],
          ['longblob', 'longblob', MysqlAdapter::BLOB_LONG, MysqlAdapter::BLOB_LONG],
        ];
    }

    /** @dataProvider blobColumnsData */
    public function testblobColumns(string $type, string $expectedType, ?int $limit, int $expectedLimit)
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', $type, ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertSame($expectedType, $sqlType['name']);
        $this->assertSame($expectedLimit, $columns[1]->getLimit());
    }

    public function testBigIntegerColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_BIG])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('bigint', $sqlType['name']);
    }

    public function testMediumIntegerColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('mediumint', $sqlType['name']);
    }

    public function testSmallIntegerColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_SMALL])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('smallint', $sqlType['name']);
    }

    public function testTinyIntegerColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => MysqlAdapter::INT_TINY])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals('tinyint', $sqlType['name']);
    }

    public function testIntegerColumnLimit()
    {
        $limit = 8;
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer', ['limit' => $limit])
              ->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($this->usingMysql8() ? 11 : $limit, $sqlType['limit']);
    }

    public function testDatetimeColumn()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'datetime')->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertNull($sqlType['limit']);
    }

    public function testDatetimeColumnLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $limit = 6;
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'datetime', ['limit' => $limit])->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    public function testTimeColumnLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $limit = 3;
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'time', ['limit' => $limit])->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    public function testTimestampColumnLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $limit = 1;
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'timestamp', ['limit' => $limit])->save();
        $columns = $table->getColumns();
        $sqlType = $this->adapter->getSqlType($columns[1]->getType(), $columns[1]->getLimit());
        $this->assertEquals($limit, $sqlType['limit']);
    }

    public function testTimestampInvalidLimit()
    {
        $this->adapter->connect();
        if (version_compare($this->adapter->getAttribute(PDO::ATTR_SERVER_VERSION), '5.6.4') === -1) {
            $this->markTestSkipped('Cannot test datetime limit on versions less than 5.6.4');
        }
        $table = new Table('t', [], $this->adapter);

        $this->expectException(PDOException::class);

        $table->addColumn('column1', 'timestamp', ['limit' => 7])->save();
    }

    public function testDropColumn()
    {
        $table = new Table('t', [], $this->adapter);
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
            ['column13', 'boolean', ['comment' => 'Lorem ipsum']],
            ['column14', 'string', ['limit' => 10]],
            ['column16', 'geometry', []],
            ['column17', 'point', []],
            ['column18', 'linestring', []],
            ['column19', 'polygon', []],
            ['column20', 'uuid', []],
            ['column21', 'set', ['values' => ['one', 'two']]],
            ['column22', 'enum', ['values' => ['three', 'four']]],
            ['enum_quotes', 'enum', ['values' => [
                "'", '\'\n', '\\', ',', '', "\\\n", '\\n', "\n", "\r", "\r\n", '/', ',,', "\t",
            ]]],
            ['column23', 'bit', []],
        ];
    }

    /**
     * @dataProvider columnsProvider
     */
    public function testGetColumns($colName, $type, $options)
    {
        $table = new Table('t', [], $this->adapter);
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

        if (isset($options['comment'])) {
            $this->assertEquals($options['comment'], $columns[1]->getComment());
        }
    }

    public function testGetColumnsInteger()
    {
        $colName = 'column15';
        $type = 'integer';
        $options = ['limit' => 10];
        $table = new Table('t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());
        $this->assertEquals($type, $columns[1]->getType());

        $this->assertEquals($this->usingMysql8() ? null : 10, $columns[1]->getLimit());
    }

    public function testDescribeTable()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string');
        $table->save();

        $described = $this->adapter->describeTable('t');

        $this->assertContains($described['TABLE_TYPE'], ['VIEW', 'BASE TABLE']);
        $this->assertEquals($described['TABLE_NAME'], 't');
        $this->assertEquals($described['TABLE_SCHEMA'], MYSQL_DB_CONFIG['name']);
        $this->assertEquals($described['TABLE_ROWS'], 0);
    }

    public function testGetColumnsReservedTableName()
    {
        $table = new Table('group', [], $this->adapter);
        $table->addColumn('column1', 'string')->save();
        $columns = $this->adapter->getColumns('group');
        $this->assertCount(2, $columns);
    }

    public function testAddIndex()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
    }

    public function testAddIndexWithSort()
    {
        $this->adapter->connect();
        if (!$this->usingMysql8()) {
            $this->markTestSkipped('Cannot test index order on mysql versions less than 8');
        }
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndexByName('table1_email_username'));
        $table->addIndex(['email', 'username'], ['name' => 'table1_email_username', 'order' => ['email' => 'DESC', 'username' => 'ASC']])
              ->save();
        $this->assertTrue($table->hasIndexByName('table1_email_username'));
        $rows = $this->adapter->fetchAll("SHOW INDEXES FROM table1 WHERE Key_name = 'table1_email_username' AND Column_name = 'email'");
        $emailOrder = $rows[0]['Collation'];
        $this->assertEquals($emailOrder, 'D');

        $rows = $this->adapter->fetchAll("SHOW INDEXES FROM table1 WHERE Key_name = 'table1_email_username' AND Column_name = 'username'");
        $emailOrder = $rows[0]['Collation'];
        $this->assertEquals($emailOrder, 'A');
    }

    public function testAddMultipleFulltextIndex()
    {
        $table = new Table('table1', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['limit' => 50])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email"',
            MYSQL_DB_CONFIG['name']
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 50);
    }

    public function testAddMultiIndexesWithLimitSpecifier()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('username', 'string')
              ->save();
        $this->assertFalse($table->hasIndex(['email', 'username']));
        $table->addIndex(['email', 'username'], ['limit' => [ 'email' => 3, 'username' => 2 ]])
              ->save();
        $this->assertTrue($table->hasIndex(['email', 'username']));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "email"',
            MYSQL_DB_CONFIG['name']
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 3);
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "username"',
            MYSQL_DB_CONFIG['name']
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 2);
    }

    public function testAddSingleIndexesWithLimitSpecifier()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addColumn('username', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['limit' => [ 'email' => 3, 2 ]])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $index_data = $this->adapter->query(sprintf(
            'SELECT SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = "%s" AND TABLE_NAME = "table1" AND INDEX_NAME = "email" AND COLUMN_NAME = "email"',
            MYSQL_DB_CONFIG['name']
        ))->fetch(PDO::FETCH_ASSOC);
        $expected_limit = $index_data['SUB_PART'];
        $this->assertEquals($expected_limit, 3);
    }

    public function testDropIndex()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email')
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $table->removeIndex(['email'])->save();
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $table2->removeIndex(['fname', 'lname'])->save();
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // index with name specified, but dropping it by column name
        $table3 = new Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'someindexname'])
              ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $table3->removeIndex(['email'])->save();
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new Table('table4', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $table4->removeIndex(['fname', 'lname'])->save();
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));

        // don't drop multiple column index when dropping single column
        $table2 = new Table('table5', [], $this->adapter);
        $table2->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'])
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));

        try {
            $table2->removeIndex(['fname'])->save();
        } catch (InvalidArgumentException $e) {
        }
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));

        // don't drop multiple column index with name specified when dropping
        // single column
        $table4 = new Table('table6', [], $this->adapter);
        $table4->addColumn('fname', 'string')
               ->addColumn('lname', 'string')
               ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
               ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));

        try {
            $table4->removeIndex(['fname'])->save();
        } catch (InvalidArgumentException $e) {
        }

        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByName()
    {
        // single column index
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
              ->addIndex('email', ['name' => 'myemailindex'])
              ->save();
        $this->assertTrue($table->hasIndex('email'));
        $table->removeIndexByName('myemailindex')->save();
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
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
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testAddForeignKeyForTableWithSignedPK()
    {
        $refTable = new Table('ref_table', ['signed' => true], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyWithMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string', ['limit' => 8])
            ->addColumn('field2', 'string', ['limit' => 8])
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->addIndex(['field1', 'id'], ['unique' => true])
            ->addIndex(['id', 'field1', 'field2'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string', ['limit' => 8])
            ->addColumn('ref_table_field2', 'string', ['limit' => 8])
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1']
            )
            ->addForeignKey(
                ['ref_table_field1', 'ref_table_id'],
                'ref_table',
                ['field1', 'id']
            )
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1', 'ref_table_field2'],
                'ref_table',
                ['id', 'field1', 'field2']
            )
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertTrue(
            $this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1', 'ref_table_field2']),
            'dropForeignKey() should only affect foreign keys that comprise of exactly the given columns'
        );
        $this->assertTrue(
            $this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']),
            'dropForeignKey() should only affect foreign keys that comprise of columns in exactly the given order'
        );

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']));
        $this->adapter->dropForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_field1', 'ref_table_id']));
    }

    public function testDropForeignKeyWithIdenticalMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string', ['limit' => 8])
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string', ['limit' => 8])
            ->addForeignKeyWithName(
                'ref_table_fk_1',
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1'],
            )
            ->addForeignKeyWithName(
                'ref_table_fk_2',
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1']
            )
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_1'));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_2'));

        $this->adapter->dropForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']);

        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id', 'ref_table_field1']));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_1'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'ref_table_fk_2'));
    }

    public function nonExistentForeignKeyColumnsProvider(): array
    {
        return [
            [['ref_table_id']],
            [['ref_table_field1']],
            [['ref_table_field1', 'ref_table_id']],
            [['non_existent_column']],
        ];
    }

    /**
     * @dataProvider nonExistentForeignKeyColumnsProvider
     * @param array $columns
     */
    public function testDropForeignKeyByNonExistentKeyColumns(array $columns)
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string', ['limit' => 8])
            ->addIndex(['id', 'field1'])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string', ['limit' => 8])
            ->addForeignKey(
                ['ref_table_id', 'ref_table_field1'],
                'ref_table',
                ['id', 'field1']
            )
            ->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'No foreign key on column(s) `%s` exists',
            implode(', ', $columns)
        ));

        $this->adapter->dropForeignKey($table->getName(), $columns);
    }

    public function testDropForeignKeyCaseInsensitivity()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), ['REF_TABLE_ID']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyByName()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), [], 'my_constraint');
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyForTableWithSignedPK()
    {
        $refTable = new Table('ref_table', ['signed' => true], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyAsString()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey('ref_table_id')->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    /**
     * @dataProvider provideForeignKeysToCheck
     */
    public function testHasForeignKey($tableDef, $key, $exp)
    {
        $conn = $this->adapter->getConnection();
        $conn->exec('CREATE TABLE other(a int, b int, c int, key(a), key(b), key(a,b), key(a,b,c));');
        $conn->exec($tableDef);
        $this->assertSame($exp, $this->adapter->hasForeignKey('t', $key));
    }

    public function provideForeignKeysToCheck()
    {
        return [
            ['create table t(a int)', 'a', false],
            ['create table t(a int)', [], false],
            ['create table t(a int primary key)', 'a', false],
            ['create table t(a int, foreign key (a) references other(a))', 'a', true],
            ['create table t(a int, foreign key (a) references other(b))', 'a', true],
            ['create table t(a int, foreign key (a) references other(b))', ['a'], true],
            ['create table t(a int, foreign key (a) references other(b))', ['a', 'a'], false],
            ['create table t(a int, foreign key(a) references other(a))', 'a', true],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', 'a', false],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['b', 'a'], false],
            ['create table t(a int, `B` int, foreign key(a,`B`) references other(a,b))', ['a', 'b'], true],
            ['create table t(a int, b int, foreign key(a,b) references other(a,b))', ['a', 'B'], true],
            ['create table t(a int, b int, c int, foreign key(a,b,c) references other(a,b,c))', ['a', 'b'], false],
            ['create table t(a int, foreign key(a) references other(a))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t(a int, b int, foreign key(a) references other(a), foreign key(b) references other(b))', ['a', 'b'], false],
            ['create table t(`0` int, foreign key(`0`) references other(a))', '0', true],
            ['create table t(`0` int, foreign key(`0`) references other(a))', '0e0', false],
            ['create table t(`0e0` int, foreign key(`0e0`) references other(a))', '0', false],
        ];
    }

    public function testHasForeignKeyAsString()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), 'ref_table_id'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), 'ref_table_id2'));
    }

    public function testHasNamedForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), [], 'my_constraint2'));
    }

    public function testHasForeignKeyWithConstraintForTableWithSignedPK()
    {
        $refTable = new Table('ref_table', ['signed' => true], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint'));
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id'], 'my_constraint2'));
    }

    public function testsHasForeignKeyWithSchemaDotTableName()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey(MYSQL_DB_CONFIG['name'] . '.' . $table->getName(), ['ref_table_id']));
        $this->assertFalse($this->adapter->hasForeignKey(MYSQL_DB_CONFIG['name'] . '.' . $table->getName(), ['ref_table_id2']));
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(MYSQL_DB_CONFIG['name']));
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['comment' => $comment = 'Comments from "column1"'])
              ->save();

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT COLUMN_NAME, COLUMN_COMMENT
            FROM information_schema.columns
            WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='table1'
            ORDER BY ORDINAL_POSITION",
            MYSQL_DB_CONFIG['name']
        ));
        $columnWithComment = $rows[1];

        $this->assertSame('column1', $columnWithComment['COLUMN_NAME'], "Didn't set column name correctly");
        $this->assertEquals($comment, $columnWithComment['COLUMN_COMMENT'], "Didn't set column comment correctly");
    }

    public function testAddGeoSpatialColumns()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('geo_geom'));
        $table->addColumn('geo_geom', 'geometry')
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals('geometry', $rows[1]['Type']);
    }

    public function testAddSetColumn()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('set_column'));
        $table->addColumn('set_column', 'set', ['values' => ['one', 'two']])
              ->save();
        $rows = $this->adapter->fetchAll('SHOW COLUMNS FROM table1');
        $this->assertEquals("set('one','two')", $rows[1]['Type']);
    }

    public function testAddEnumColumn()
    {
        $table = new Table('table1', [], $this->adapter);
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
        (new Table('table1', [], $this->adapter))
            ->addColumn('enum_column', 'enum', ['values' => ['one', 'two']])
            ->save();

        // Reading them back
        $table = new Table('table1', [], $this->adapter);
        $columns = $table->getColumns();
        $enumColumn = end($columns);
        $this->assertEquals(AdapterInterface::PHINX_TYPE_ENUM, $enumColumn->getType());
        $this->assertEquals(['one', 'two'], $enumColumn->getValues());
    }

    public function testEnumColumnWithNullValue()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('enum_column', 'enum', ['values' => ['one', 'two', null]]);

        $this->expectException(PDOException::class);
        $table->save();
    }

    public function testHasColumn()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $this->assertFalse($table->hasColumn('column2'));
        $this->assertTrue($table->hasColumn('column1'));
    }

    public function testHasColumnReservedName()
    {
        $tableQuoted = new Table('group', [], $this->adapter);
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
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
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
            ],
        ];
        $table = new Table('table1', [], $this->adapter);
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

        $table = new Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test', 'null' => false])
            ->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`id` INT(11) unsigned NOT NULL AUTO_INCREMENT, `column1` VARCHAR(255) NOT NULL, `column2` INT(11) NULL, `column3` VARCHAR(255) NOT NULL DEFAULT 'test', PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create table query to the output');
    }

    /**
     * Creates the table "table1".
     * Then sets phinx to dry run mode and inserts a record.
     * Asserts that phinx outputs the insert statement and doesn't insert a record.
     */
    public function testDumpInsert()
    {
        $table = new Table('table1', [], $this->adapter);
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

        // Add this to be LF - CR/LF systems independent
        $expectedOutput = preg_replace('~\R~u', '', $expectedOutput);
        $actualOutput = preg_replace('~\R~u', '', $actualOutput);

        $this->assertStringContainsString($expectedOutput, trim($actualOutput), 'Passing the --dry-run option doesn\'t dump the insert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
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
        $table = new Table('table1', [], $this->adapter);
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
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option doesn\'t dump the bulkinsert to the output');

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        $this->assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0]['COUNT(*)']);
    }

    public function testDumpCreateTableAndThenInsert()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $table = new Table('table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);

        $table->addColumn('column1', 'string', ['null' => false])
            ->addColumn('column2', 'integer')
            ->save();

        $table = new Table('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`column1` VARCHAR(255) NOT NULL, `column2` INT(11) NULL, PRIMARY KEY (`column1`)) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
INSERT INTO `table1` (`column1`, `column2`) VALUES ('id1', 1);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        // Add this to be LF - CR/LF systems independent
        $expectedOutput = preg_replace('~\R~u', '', $expectedOutput);
        $actualOutput = preg_replace('~\R~u', '', $actualOutput);
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
    }

    public function testDumpTransaction()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $this->adapter->beginTransaction();
        $table = new Table('table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();
        $this->adapter->commitTransaction();
        $this->adapter->rollbackTransaction();

        $actualOutput = $consoleOutput->fetch();
        // Add this to be LF - CR/LF systems independent
        $actualOutput = preg_replace('~\R~u', '', $actualOutput);
        $this->assertStringStartsWith('START TRANSACTION;', $actualOutput, 'Passing the --dry-run doesn\'t dump the transaction to the output');
        $this->assertStringEndsWith('COMMIT;ROLLBACK;', $actualOutput, 'Passing the --dry-run doesn\'t dump the transaction to the output');
    }

    /**
     * Tests interaction with the query builder
     */
    public function testQueryBuilder()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $builder = $this->adapter->getQueryBuilder(Query::TYPE_INSERT);
        $stm = $builder
            ->insert(['string_col', 'int_col'])
            ->into('table1')
            ->values(['string_col' => 'value1', 'int_col' => 1])
            ->values(['string_col' => 'value2', 'int_col' => 2])
            ->execute();

        $this->assertEquals(2, $stm->rowCount());

        $builder = $this->adapter->getQueryBuilder(Query::TYPE_SELECT);
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

        $builder = $this->adapter->getQueryBuilder(query::TYPE_DELETE);
        $stm = $builder
            ->delete('table1')
            ->where(['int_col <' => 2])
            ->execute();

        $this->assertEquals(1, $stm->rowCount());
    }

    public function testQueryWithParams()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('string_col', 'string')
            ->addColumn('int_col', 'integer')
            ->save();

        $this->adapter->insert($table->getTable(), [
            'string_col' => 'test data',
            'int_col' => 10,
        ]);

        $this->adapter->insert($table->getTable(), [
            'string_col' => null,
        ]);

        $this->adapter->insert($table->getTable(), [
            'int_col' => 23,
        ]);

        $countQuery = $this->adapter->query('SELECT COUNT(*) AS c FROM table1 WHERE int_col > ?', [5]);
        $res = $countQuery->fetchAll();
        $this->assertEquals(2, $res[0]['c']);

        $this->adapter->execute('UPDATE table1 SET int_col = ? WHERE int_col IS NULL', [12]);

        $countQuery->execute([1]);
        $res = $countQuery->fetchAll();
        $this->assertEquals(3, $res[0]['c']);
    }

    public function testLiteralSupport()
    {
        $createQuery = <<<'INPUT'
CREATE TABLE `test` (`double_col` double NOT NULL)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new Table('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('double'), array_pop($columns)->getType());
    }

    public function geometryTypeProvider()
    {
        return [
            [MysqlAdapter::PHINX_TYPE_GEOMETRY, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_POINT, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_LINESTRING, 'LINESTRING(30 10,10 30,40 40)'],
            [MysqlAdapter::PHINX_TYPE_POLYGON, 'POLYGON((30 10,40 40,20 40,10 20,30 10))'],
        ];
    }

    /**
     * @dataProvider geometryTypeProvider
     * @param string $type
     * @param string $geom
     */
    public function testGeometrySridSupport($type, $geom)
    {
        $this->adapter->connect();
        if (!$this->usingMysql8()) {
            $this->markTestSkipped('Cannot test geometry srid on mysql versions less than 8');
        }

        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('geom', $type, ['srid' => 4326])
            ->save();

        $this->adapter->execute("INSERT INTO table1 (`geom`) VALUES (ST_GeomFromText('{$geom}', 4326))");
        $rows = $this->adapter->fetchAll('SELECT ST_AsWKT(geom) as wkt, ST_SRID(geom) as srid FROM table1');
        $this->assertCount(1, $rows);
        $this->assertSame($geom, $rows[0]['wkt']);
        $this->assertSame(4326, (int)$rows[0]['srid']);
    }

    /**
     * @dataProvider geometryTypeProvider
     * @param string $type
     * @param string $geom
     */
    public function testGeometrySridThrowsInsertDifferentSrid($type, $geom)
    {
        $this->adapter->connect();
        if (!$this->usingMysql8()) {
            $this->markTestSkipped('Cannot test geometry srid on mysql versions less than 8');
        }

        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('geom', $type, ['srid' => 4326])
            ->save();

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("SQLSTATE[HY000]: General error: 3643 The SRID of the geometry does not match the SRID of the column 'geom'. The SRID of the geometry is 4322, but the SRID of the column is 4326. Consider changing the SRID of the geometry or the SRID property of the column.");
        $this->adapter->execute("INSERT INTO table1 (`geom`) VALUES (ST_GeomFromText('{$geom}', 4322))");
    }

    /**
     * Small check to verify if specific Mysql constants are handled in AdapterInterface
     *
     * @see https://github.com/cakephp/migrations/issues/359
     */
    public function testMysqlBlobsConstants()
    {
        $reflector = new ReflectionClass(AdapterInterface::class);

        $validTypes = array_filter($reflector->getConstants(), function ($constant) {
            return substr($constant, 0, strlen('PHINX_TYPE_')) === 'PHINX_TYPE_';
        }, ARRAY_FILTER_USE_KEY);

        $this->assertTrue(in_array('tinyblob', $validTypes, true));
        $this->assertTrue(in_array('blob', $validTypes, true));
        $this->assertTrue(in_array('mediumblob', $validTypes, true));
        $this->assertTrue(in_array('longblob', $validTypes, true));
    }

    public function defaultsCastAsExpressions()
    {
        return [
            [MysqlAdapter::PHINX_TYPE_BLOB, 'abc'],
            [MysqlAdapter::PHINX_TYPE_JSON, '{"a": true}'],
            [MysqlAdapter::PHINX_TYPE_TEXT, 'abc'],
            [MysqlAdapter::PHINX_TYPE_GEOMETRY, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_POINT, 'POINT(0 0)'],
            [MysqlAdapter::PHINX_TYPE_LINESTRING, 'LINESTRING(30 10,10 30,40 40)'],
            [MysqlAdapter::PHINX_TYPE_POLYGON, 'POLYGON((30 10,40 40,20 40,10 20,30 10))'],
        ];
    }

    /**
     * MySQL 8 added support for specifying defaults for the BLOB, TEXT, GEOMETRY, and JSON data types,
     * however requiring that they be wrapped in expressions.
     *
     * @dataProvider defaultsCastAsExpressions
     * @param string $type
     * @param string $default
     */
    public function testDefaultsCastAsExpressionsForCertainTypes(string $type, string $default): void
    {
        $this->adapter->connect();

        $table = new Table('table1', ['id' => false], $this->adapter);
        if (!$this->usingMysql8()) {
            $this->expectException(PDOException::class);
        }
        $table
            ->addColumn('col_1', $type, ['default' => $default])
            ->create();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(1, $columns);
        $this->assertSame('col_1', $columns[0]->getName());
        $this->assertSame($default, $columns[0]->getDefault());
    }

    public function testCreateTableWithPrecisionCurrentTimestamp()
    {
        $this->adapter->connect();
        (new Table('exampleCurrentTimestamp3', ['id' => false], $this->adapter))
            ->addColumn('timestamp_3', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP(3)',
                'limit' => 3,
            ])
            ->create();

        $rows = $this->adapter->fetchAll(sprintf(
            "SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='exampleCurrentTimestamp3'",
            MYSQL_DB_CONFIG['name']
        ));
        $colDef = $rows[0];
        $this->assertEqualsIgnoringCase('CURRENT_TIMESTAMP(3)', $colDef['COLUMN_DEFAULT']);
    }

    public function pdoAttributeProvider()
    {
        return [
            ['mysql_attr_invalid'],
            ['attr_invalid'],
        ];
    }

    /**
     * @dataProvider pdoAttributeProvider
     */
    public function testInvalidPdoAttribute($attribute)
    {
        $adapter = new MysqlAdapter(MYSQL_DB_CONFIG + [$attribute => true]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid PDO attribute: ' . $attribute . ' (\PDO::' . strtoupper($attribute) . ')');
        $adapter->connect();
    }

    public function integerDataTypesSQLProvider()
    {
        return [
            // Types without a width should always have a null limit
            ['bigint', ['name' => AdapterInterface::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'scale' => null]],
            ['int', ['name' => AdapterInterface::PHINX_TYPE_INTEGER, 'limit' => null, 'scale' => null]],
            ['mediumint', ['name' => AdapterInterface::PHINX_TYPE_MEDIUM_INTEGER, 'limit' => null, 'scale' => null]],
            ['smallint', ['name' => AdapterInterface::PHINX_TYPE_SMALL_INTEGER, 'limit' => null, 'scale' => null]],
            ['tinyint', ['name' => AdapterInterface::PHINX_TYPE_TINY_INTEGER, 'limit' => null, 'scale' => null]],

            // Types which include a width should always have that as their limit
            ['bigint(20)', ['name' => AdapterInterface::PHINX_TYPE_BIG_INTEGER, 'limit' => 20, 'scale' => null]],
            ['bigint(10)', ['name' => AdapterInterface::PHINX_TYPE_BIG_INTEGER, 'limit' => 10, 'scale' => null]],
            ['bigint(1) unsigned', ['name' => AdapterInterface::PHINX_TYPE_BIG_INTEGER, 'limit' => 1, 'scale' => null]],
            ['int(11)', ['name' => AdapterInterface::PHINX_TYPE_INTEGER, 'limit' => 11, 'scale' => null]],
            ['int(10) unsigned', ['name' => AdapterInterface::PHINX_TYPE_INTEGER, 'limit' => 10, 'scale' => null]],
            ['mediumint(6)', ['name' => AdapterInterface::PHINX_TYPE_MEDIUM_INTEGER, 'limit' => 6, 'scale' => null]],
            ['mediumint(8) unsigned', ['name' => AdapterInterface::PHINX_TYPE_MEDIUM_INTEGER, 'limit' => 8, 'scale' => null]],
            ['smallint(2)', ['name' => AdapterInterface::PHINX_TYPE_SMALL_INTEGER, 'limit' => 2, 'scale' => null]],
            ['smallint(5) unsigned', ['name' => AdapterInterface::PHINX_TYPE_SMALL_INTEGER, 'limit' => 5, 'scale' => null]],
            ['tinyint(3) unsigned', ['name' => AdapterInterface::PHINX_TYPE_TINY_INTEGER, 'limit' => 3, 'scale' => null]],
            ['tinyint(4)', ['name' => AdapterInterface::PHINX_TYPE_TINY_INTEGER, 'limit' => 4, 'scale' => null]],

            // Special case for commonly used boolean type
            ['tinyint(1)', ['name' => AdapterInterface::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
        ];
    }

    /**
     * @dataProvider integerDataTypesSQLProvider
     */
    public function testGetPhinxTypeFromSQLDefinition(string $sqlDefinition, array $expectedResponse)
    {
        $result = $this->adapter->getPhinxType($sqlDefinition);

        $this->assertSame($expectedResponse['name'], $result['name'], "Type mismatch - got '{$result['name']}' when expecting '{$expectedResponse['name']}'");
        $this->assertSame($expectedResponse['limit'], $result['limit'], "Field upper boundary mismatch - got '{$result['limit']}' when expecting '{$expectedResponse['limit']}'");
    }

    public function testPdoPersistentConnection()
    {
        $adapter = new MysqlAdapter(MYSQL_DB_CONFIG + ['attr_persistent' => true]);
        $this->assertTrue($adapter->getConnection()->getAttribute(PDO::ATTR_PERSISTENT));
    }

    public function testPdoNotPersistentConnection()
    {
        $adapter = new MysqlAdapter(MYSQL_DB_CONFIG);
        $this->assertFalse($adapter->getConnection()->getAttribute(PDO::ATTR_PERSISTENT));
    }
}
