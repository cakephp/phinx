<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Adapter;

use BadMethodCallException;
use Cake\Database\Query;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Adapter\UnsupportedColumnTypeException;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Util\Expression;
use Phinx\Util\Literal;
use ReflectionObject;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Test\Phinx\TestCase;
use UnexpectedValueException;

class SQLiteAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\SQLiteAdapter
     */
    private $adapter;

    protected function setUp(): void
    {
        if (!defined('SQLITE_DB_CONFIG')) {
            $this->markTestSkipped('SQLite tests disabled.');
        }

        $this->adapter = new SQLiteAdapter(SQLITE_DB_CONFIG, new ArrayInput([]), new NullOutput());

        if (SQLITE_DB_CONFIG['name'] !== ':memory:') {
            // ensure the database is empty for each test
            $this->adapter->dropDatabase(SQLITE_DB_CONFIG['name']);
            $this->adapter->createDatabase(SQLITE_DB_CONFIG['name']);
        }

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
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

    public function testBeginTransaction()
    {
        $this->adapter->beginTransaction();

        $this->assertTrue(
            $this->adapter->getConnection()->inTransaction(),
            'Underlying PDO instance did not detect new transaction'
        );
    }

    public function testRollbackTransaction()
    {
        $this->adapter->getConnection()
            ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->adapter->beginTransaction();
        $this->adapter->rollbackTransaction();

        $this->assertFalse(
            $this->adapter->getConnection()->inTransaction(),
            'Underlying PDO instance did not detect rolled back transaction'
        );
    }

    public function testCommitTransactionTransaction()
    {
        $this->adapter->getConnection()
            ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

        //ensure the primary key is not nullable
        /** @var \Phinx\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertTrue($idColumn->getIdentity());
        $this->assertFalse($idColumn->isNull());
    }

    public function testCreateTableIdentityIdColumn()
    {
        $table = new Table('ntable', ['id' => false, 'primary_key' => ['custom_id']], $this->adapter);
        $table->addColumn('custom_id', 'integer', ['identity' => true])
            ->save();

        $this->assertTrue($this->adapter->hasTable('ntable'));
        $this->assertTrue($this->adapter->hasColumn('ntable', 'custom_id'));

        /** @var \Phinx\Db\Table\Column $idColumn */
        $idColumn = $this->adapter->getColumns('ntable')[0];
        $this->assertTrue($idColumn->getIdentity());
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

    public function testCreateTableWithMultiplePrimaryKeys()
    {
        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id'],
        ];
        $table = new Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('table1', ['USER_ID', 'tag_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'USER_ID']));
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
        $table->addColumn('id', 'uuid')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
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
        $table->addColumn('id', 'binaryuuid')->save();
        $this->assertTrue($this->adapter->hasColumn('ztable', 'id'));
        $this->assertTrue($this->adapter->hasIndex('ztable', 'id'));
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
        $table->addColumn('email', 'string')
            ->addIndex('email', ['unique' => true])
            ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', ['email']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['email', 'user_email']));
    }

    public function testCreateTableWithNamedIndexes()
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

    public function testCreateTableWithForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn('ref_table_id', 'integer');
        $table->addForeignKey('ref_table_id', 'ref_table', 'id');
        $table->save();

        $this->assertTrue($this->adapter->hasTable($table->getName()));
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testCreateTableWithIndexesAndForeignKey()
    {
        $refTable = new Table('tbl_master', [], $this->adapter);
        $refTable->create();

        $table = new Table('tbl_child', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->addColumn('master_id', 'integer')
            ->addIndex(['column2'])
            ->addIndex(['column1', 'column2'], ['unique' => true, 'name' => 'uq_tbl_child_column1_column2_ndx'])
            ->addForeignKey(
                'master_id',
                'tbl_master',
                'id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_master_id']
            )
            ->create();

        $this->assertTrue($this->adapter->hasIndex('tbl_child', 'column2'));
        $this->assertTrue($this->adapter->hasIndex('tbl_child', ['column1', 'column2']));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['master_id']));

        $row = $this->adapter->fetchRow(
            "SELECT * FROM sqlite_master WHERE `type` = 'table' AND `tbl_name` = 'tbl_child'"
        );
        $this->assertStringContainsString(
            'CONSTRAINT `fk_master_id` FOREIGN KEY (`master_id`) REFERENCES `tbl_master` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION',
            $row['sql']
        );
    }

    public function testCreateTableWithoutAutoIncrementingPrimaryKeyAndWithForeignKey()
    {
        $refTable = (new Table('tbl_master', ['id' => false, 'primary_key' => 'id'], $this->adapter))
            ->addColumn('id', 'text');
        $refTable->create();

        $table = (new Table('tbl_child', ['id' => false, 'primary_key' => 'master_id'], $this->adapter))
            ->addColumn('master_id', 'text')
            ->addForeignKey(
                'master_id',
                'tbl_master',
                'id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION', 'constraint' => 'fk_master_id']
            );
        $table->create();

        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['master_id']));

        $row = $this->adapter->fetchRow(
            "SELECT * FROM sqlite_master WHERE `type` = 'table' AND `tbl_name` = 'tbl_child'"
        );
        $this->assertStringContainsString(
            'CONSTRAINT `fk_master_id` FOREIGN KEY (`master_id`) REFERENCES `tbl_master` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION',
            $row['sql']
        );
    }

    public function testAddPrimaryKey()
    {
        $table = new Table('table1', ['id' => false], $this->adapter);
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
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
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
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
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
        $table = new Table('table1', ['id' => false, 'primary_key' => 'column1'], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $table
            ->changePrimaryKey(null)
            ->save();

        $this->assertFalse($this->adapter->hasPrimaryKey('table1', ['column1']));
    }

    public function testAddMultipleColumnPrimaryKeyFails()
    {
        $table = new Table('table1', [], $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->save();

        $this->expectException(InvalidArgumentException::class);

        $table
            ->changePrimaryKey(['column1', 'column2'])
            ->save();
    }

    public function testChangeCommentFails()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();

        $this->expectException(BadMethodCallException::class);

        $table
            ->changeComment('comment1')
            ->save();
    }

    public function testRenameTable()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('table1'));
        $this->assertFalse($this->adapter->hasTable('table2'));
        $this->adapter->renameTable('table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('table1'));
        $this->assertTrue($this->adapter->hasTable('table2'));
    }

    public function testAddColumn()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string', ['null' => true])
            ->save();
        $this->assertTrue($table->hasColumn('email'));

        // In SQLite it is not possible to dictate order of added columns.
        // $table->addColumn('realname', 'string', array('after' => 'id'))
        //       ->save();
        // $this->assertEquals('realname', $rows[1]['Field']);
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("'test'", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultZero()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertNotNull($rows[1]['dflt_value']);
        $this->assertEquals('0', $rows[1]['dflt_value']);
    }

    public function testAddColumnWithDefaultEmptyString()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_empty', 'string', ['default' => ''])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals("''", $rows[1]['dflt_value']);
    }

    public function testAddColumnWithCustomType()
    {
        $this->adapter->setDataDomain([
            'custom' => [
                'type' => 'string',
                'null' => true,
                'limit' => 15,
            ],
        ]);

        (new Table('table1', [], $this->adapter))
            ->addColumn('custom', 'custom')
            ->addColumn('custom_ext', 'custom', [
                'null' => false,
                'limit' => 30,
            ])
            ->save();

        $this->assertTrue($this->adapter->hasTable('table1'));

        $columns = $this->adapter->getColumns('table1');
        $this->assertArrayHasKey(1, $columns);
        $this->assertArrayHasKey(2, $columns);

        $column = $this->adapter->getColumns('table1')[1];
        $this->assertSame('custom', $column->getName());
        $this->assertSame('string', $column->getType());
        $this->assertSame(15, $column->getLimit());
        $this->assertTrue($column->getNull());

        $column = $this->adapter->getColumns('table1')[2];
        $this->assertSame('custom_ext', $column->getName());
        $this->assertSame('string', $column->getType());
        $this->assertSame(30, $column->getLimit());
        $this->assertFalse($column->getNull());
    }

    public function irregularCreateTableProvider()
    {
        return [
            ["CREATE TABLE \"users\"\n( `id` INTEGER NOT NULL )", ['id', 'foo']],
            ['CREATE TABLE users   (    id INTEGER NOT NULL )', ['id', 'foo']],
            ["CREATE TABLE [users]\n(\nid INTEGER NOT NULL)", ['id', 'foo']],
            ["CREATE TABLE \"users\" ([id] \n INTEGER NOT NULL\n, \"bar\" INTEGER)", ['id', 'bar', 'foo']],
        ];
    }

    /**
     * @dataProvider irregularCreateTableProvider
     */
    public function testAddColumnToIrregularCreateTableStatements(string $createTableSql, array $expectedColumns): void
    {
        $this->adapter->execute($createTableSql);
        $table = new Table('users', [], $this->adapter);
        $table->addColumn('foo', 'string');
        $table->update();

        $columns = $this->adapter->getColumns('users');
        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertEquals($expectedColumns[$i], $columns[$i]->getName());
        }
    }

    public function testAddDoubleColumn()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('foo', 'double', ['null' => true])
            ->save();
        $rows = $this->adapter->fetchAll(sprintf('pragma table_info(%s)', 'table1'));
        $this->assertEquals('DOUBLE', $rows[1]['type']);
    }

    public function testRenameColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenamingANonExistentColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The specified column doesn't exist: column2");
        $this->adapter->renameColumn('t', 'column2', 'column1');
    }

    public function testRenameColumnWithIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));
    }

    public function testRenameColumnWithUniqueIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol', ['unique' => true])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));
    }

    public function testRenameColumnWithCompositeIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addIndex(['indexcol1', 'indexcol2'])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol2']));

        $table->renameColumn('indexcol2', 'newindexcol2')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol2']));
    }

    /**
     * Tests that rewriting the index SQL does not accidentally change
     * the table name in case it matches the column name.
     */
    public function testRenameColumnWithIndexMatchingTheTableName()
    {
        $table = new Table('indexcol', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));
    }

    /**
     * Tests that rewriting the index SQL does not accidentally change
     * column names that partially match the column to rename.
     */
    public function testRenameColumnWithIndexColumnPartialMatch()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addColumn('indexcolumn', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (indexcolumn, indexcol)');

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'indexcol']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'newindexcol']));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'indexcol']));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcolumn', 'newindexcol']));
    }

    public function testRenameColumnWithIndexColumnRequiringQuoting()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'new index col'));

        $table->renameColumn('indexcol', 'new index col')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'new index col'));
    }

    /**
     * Indices that are using expressions are not being updated.
     */
    public function testRenameColumnWithExpressionIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (`indexcol`, ABS(`indexcol`))');

        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: indexcol');

        $table->renameColumn('indexcol', 'newindexcol')->update();
    }

    /**
     * Index SQL is mostly returned as-is, hence custom indices can contain
     * a wide variety of formats.
     */
    public function customIndexSQLDataProvider(): array
    {
        return [
            [
                'CREATE INDEX test_idx ON t(indexcol);',
                'CREATE INDEX test_idx ON t(`newindexcol`)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol`);',
                'CREATE INDEX test_idx ON t(`newindexcol`)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol");',
                'CREATE INDEX test_idx ON t(`newindexcol`)',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol]);',
                'CREATE INDEX test_idx ON t(`newindexcol`)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol ASC);',
                'CREATE INDEX test_idx ON t(`newindexcol` ASC)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol` ASC);',
                'CREATE INDEX test_idx ON t(`newindexcol` ASC)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol" DESC);',
                'CREATE INDEX test_idx ON t(`newindexcol` DESC)',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol] DESC);',
                'CREATE INDEX test_idx ON t(`newindexcol` DESC)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol COLLATE BINARY);',
                'CREATE INDEX test_idx ON t(`newindexcol` COLLATE BINARY)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol COLLATE BINARY ASC);',
                'CREATE INDEX test_idx ON t(`newindexcol` COLLATE BINARY ASC)',
            ],
            [
                '
                    cReATE uniQUE inDEx
                        iF   nOT   ExISts
                            main.test_idx   on   t  (
                                ( ((
                                    inDEXcoL
                                ) )) COLLATE   BINARY   ASC
                            );
                ',
                'CREATE UNIQUE INDEX test_idx   on   t  (
                                ( ((
                                    `newindexcol`
                                ) )) COLLATE   BINARY   ASC
                            )',
            ],
        ];
    }

    /**
     * @dataProvider customIndexSQLDataProvider
     * @param string $indexSQL Index creation SQL
     * @param string $newIndexSQL Expected new index creation SQL
     */
    public function testRenameColumnWithCustomIndex(string $indexSQL, string $newIndexSQL)
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $table->renameColumn('indexcol', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'newindexcol'));

        $index = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'test_idx'");
        $this->assertSame($newIndexSQL, $index['sql']);
    }

    /**
     * Index SQL is mostly returned as-is, hence custom indices can contain
     * a wide variety of formats.
     */
    public function customCompositeIndexSQLDataProvider(): array
    {
        return [
            [
                'CREATE INDEX test_idx ON t(indexcol1, indexcol2, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1, `newindexcol`, indexcol3)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol1`, `indexcol2`, `indexcol3`);',
                'CREATE INDEX test_idx ON t(`indexcol1`, `newindexcol`, `indexcol3`)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol1", "indexcol2", "indexcol3");',
                'CREATE INDEX test_idx ON t("indexcol1", `newindexcol`, "indexcol3")',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol1], [indexcol2], [indexcol3]);',
                'CREATE INDEX test_idx ON t([indexcol1], `newindexcol`, [indexcol3])',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol1 ASC, indexcol2 DESC, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1 ASC, `newindexcol` DESC, indexcol3)',
            ],
            [
                'CREATE INDEX test_idx ON t(`indexcol1` ASC, `indexcol2` DESC, `indexcol3`);',
                'CREATE INDEX test_idx ON t(`indexcol1` ASC, `newindexcol` DESC, `indexcol3`)',
            ],
            [
                'CREATE INDEX test_idx ON t("indexcol1" ASC, "indexcol2" DESC, "indexcol3");',
                'CREATE INDEX test_idx ON t("indexcol1" ASC, `newindexcol` DESC, "indexcol3")',
            ],
            [
                'CREATE INDEX test_idx ON t([indexcol1] ASC, [indexcol2] DESC, [indexcol3]);',
                'CREATE INDEX test_idx ON t([indexcol1] ASC, `newindexcol` DESC, [indexcol3])',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY, indexcol2 COLLATE NOCASE, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY, `newindexcol` COLLATE NOCASE, indexcol3)',
            ],
            [
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY ASC, indexcol2 COLLATE NOCASE DESC, indexcol3);',
                'CREATE INDEX test_idx ON t(indexcol1 COLLATE BINARY ASC, `newindexcol` COLLATE NOCASE DESC, indexcol3)',
            ],
            [
                '
                    cReATE uniQUE inDEx
                        iF   nOT   ExISts
                            main.test_idx   on   t  (
                                inDEXcoL1 ,
                                ( ((
                                    inDEXcoL2
                                ) )) COLLATE   BINARY   ASC ,
                                inDEXcoL3
                            );
                ',
                'CREATE UNIQUE INDEX test_idx   on   t  (
                                inDEXcoL1 ,
                                ( ((
                                    `newindexcol`
                                ) )) COLLATE   BINARY   ASC ,
                                inDEXcoL3
                            )',
            ],
        ];
    }

    /**
     * Index SQL is mostly returned as-is, hence custom indices can contain
     * a wide variety of formats.
     *
     * @dataProvider customCompositeIndexSQLDataProvider
     * @param string $indexSQL Index creation SQL
     * @param string $newIndexSQL Expected new index creation SQL
     */
    public function testRenameColumnWithCustomCompositeIndex(string $indexSQL, string $newIndexSQL)
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addColumn('indexcol3', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol', 'indexcol3']));

        $table->renameColumn('indexcol2', 'newindexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'newindexcol', 'indexcol3']));

        $index = $this->adapter->fetchRow("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'test_idx'");
        $this->assertSame($newIndexSQL, $index['sql']);
    }

    public function testChangeColumn()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
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
        $rows = $this->adapter->fetchAll('pragma table_info(t)');

        $this->assertEquals("'test1'", $rows[1]['dflt_value']);
    }

    /**
     * @group bug922
     */
    public function testChangeColumnWithForeignKey()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new Table('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $table->changeColumn('ref_table_id', 'float')->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testChangeColumnWithIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex(
                'indexcol',
                ['unique' => true]
            )
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->changeColumn('indexcol', 'integer', ['null' => false])->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testChangeColumnWithTrigger()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('triggercol', 'integer')
            ->addColumn('othercol', 'integer')
            ->create();

        $triggerSQL =
            'CREATE TRIGGER update_t_othercol UPDATE OF triggercol ON t
                BEGIN
                    UPDATE t SET othercol = new.triggercol;
                END';

        $this->adapter->execute($triggerSQL);

        $rows = $this->adapter->fetchAll(
            "SELECT * FROM sqlite_master WHERE `type` = 'trigger' AND tbl_name = 't'"
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('trigger', $rows[0]['type']);
        $this->assertEquals('update_t_othercol', $rows[0]['name']);
        $this->assertEquals($triggerSQL, $rows[0]['sql']);

        $table->changeColumn('triggercol', 'integer', ['null' => false])->update();

        $rows = $this->adapter->fetchAll(
            "SELECT * FROM sqlite_master WHERE `type` = 'trigger' AND tbl_name = 't'"
        );
        $this->assertCount(1, $rows);
        $this->assertEquals('trigger', $rows[0]['type']);
        $this->assertEquals('update_t_othercol', $rows[0]['name']);
        $this->assertEquals($triggerSQL, $rows[0]['sql']);
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
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertEquals('0', $rows[1]['dflt_value']);
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
        $rows = $this->adapter->fetchAll('pragma table_info(t)');
        $this->assertNull($rows[1]['dflt_value']);
    }

    public function testChangeColumnWithCommasInCommentsOrDefaultValue()
    {
        $table = new Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'one, two or three', 'comment' => 'three, two or one'])
            ->save();
        $newColumn1 = new Column();
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
        $table = new Table('t', [], $this->adapter);
        $columnName = $columnCreationArgs[0];
        call_user_func_array([$table, 'addColumn'], $columnCreationArgs);
        $table->save();
        $this->assertTrue($this->adapter->hasColumn('t', $columnName));

        $table->removeColumn($columnName)->save();

        $this->assertFalse($this->adapter->hasColumn('t', $columnName));
    }

    public function testDropColumnWithIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testDropColumnWithUniqueIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addIndex('indexcol', ['unique' => true])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    public function testDropColumnWithCompositeIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addIndex(['indexcol1', 'indexcol2'])
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));

        $table->removeColumn('indexcol2')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2']));
    }

    /**
     * Tests that removing columns does not accidentally drop indices
     * on table names that match the column to remove.
     */
    public function testDropColumnWithIndexMatchingTheTableName()
    {
        $table = new Table('indexcol', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addColumn('indexcolumn', 'integer')
            ->addIndex('indexcolumn')
            ->create();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));

        $table->removeColumn('indexcol')->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));
    }

    /**
     * Tests that removing columns does not accidentally drop indices
     * that contain column names that partially match the column to remove.
     */
    public function testDropColumnWithIndexColumnPartialMatch()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->addColumn('indexcolumn', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (indexcolumn)');

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));

        $table->removeColumn('indexcol')->update();

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcolumn'));
    }

    /**
     * Indices with expressions are not being removed.
     */
    public function testDropColumnWithExpressionIndex()
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute('CREATE INDEX custom_idx ON t (ABS(indexcol))');

        $this->assertTrue($this->adapter->hasIndexByName('t', 'custom_idx'));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: indexcol');

        $table->removeColumn('indexcol')->update();
    }

    /**
     * @dataProvider customIndexSQLDataProvider
     * @param string $indexSQL Index creation SQL
     */
    public function testDropColumnWithCustomIndex(string $indexSQL)
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), 'indexcol'));

        $table->removeColumn('indexcol')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), 'indexcol'));
    }

    /**
     * @dataProvider customCompositeIndexSQLDataProvider
     * @param string $indexSQL Index creation SQL
     */
    public function testDropColumnWithCustomCompositeIndex(string $indexSQL)
    {
        $table = new Table('t', [], $this->adapter);
        $table
            ->addColumn('indexcol1', 'integer')
            ->addColumn('indexcol2', 'integer')
            ->addColumn('indexcol3', 'integer')
            ->create();

        $this->adapter->execute($indexSQL);

        $this->assertTrue($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol3']));

        $table->removeColumn('indexcol2')->update();

        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol2', 'indexcol3']));
        $this->assertFalse($this->adapter->hasIndex($table->getName(), ['indexcol1', 'indexcol3']));
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
        $table = new Table('table1', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'])
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table2->getName(), ['fname', 'lname']);
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // single column index with name specified
        $table3 = new Table('table3', [], $this->adapter);
        $table3->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'someindexname'])
            ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new Table('table4', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailindex'])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new Table('table2', [], $this->adapter);
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
        $refTable = new Table('ref_table', [], $this->adapter);
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
        $refTable->addColumn('field1', 'string')
            ->addIndex(['field1'], ['unique' => true])
            ->save();

        $table = new Table('another_table', [], $this->adapter);
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

    public function testDropForeignKeyWithQuoteVariants()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')
            ->addIndex(['field1'], ['unique' => true])
            ->save();

        $this->adapter->execute("
            CREATE TABLE `table` (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                [ref_[_brackets] INTEGER NOT NULL,
                `ref_``_ticks` INTEGER NOT NULL,
                \"ref_\"\"_double_quotes\" INTEGER NOT NULL,
                'ref_''_single_quotes' INTEGER NOT NULL,
                ref_no_quotes INTEGER NOT NULL,
                ref_no_space INTEGER NOT NULL,
                ref_lots_of_space INTEGER NOT NULL,
                FOREIGN KEY ([ref_[_brackets]) REFERENCES `ref_table` (`id`),
                FOREIGN KEY (`ref_``_ticks`) REFERENCES `ref_table` (`id`),
                FOREIGN KEY (\"ref_\"\"_double_quotes\") REFERENCES `ref_table` (`id`),
                FOREIGN KEY ('ref_''_single_quotes') REFERENCES `ref_table` (`id`),
                FOREIGN KEY (ref_no_quotes) REFERENCES `ref_table` (`id`),
                FOREIGN KEY (`ref_``_ticks`, 'ref_''_single_quotes') REFERENCES `ref_table` (`id`, `field1`),
                FOREIGN KEY(`ref_no_space`,`ref_no_space`)REFERENCES`ref_table`(`id`,`id`),
                foreign      KEY
                    ( `ref_lots_of_space`		,`ref_lots_of_space`    )
                        REFErences   `ref_table`  (`id`    ,	`id`)
            )
        ");

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_[_brackets']));
        $this->adapter->dropForeignKey('table', ['ref_[_brackets']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_[_brackets']));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_"_double_quotes']));
        $this->adapter->dropForeignKey('table', ['ref_"_double_quotes']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_"_double_quotes']));

        $this->assertTrue($this->adapter->hasForeignKey('table', ["ref_'_single_quotes"]));
        $this->adapter->dropForeignKey('table', ["ref_'_single_quotes"]);
        $this->assertFalse($this->adapter->hasForeignKey('table', ["ref_'_single_quotes"]));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_no_quotes']));
        $this->adapter->dropForeignKey('table', ['ref_no_quotes']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_no_quotes']));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_`_ticks', "ref_'_single_quotes"]));
        $this->adapter->dropForeignKey('table', ['ref_`_ticks', "ref_'_single_quotes"]);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_`_ticks', "ref_'_single_quotes"]));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_no_space', 'ref_no_space']));
        $this->adapter->dropForeignKey('table', ['ref_no_space', 'ref_no_space']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_no_space', 'ref_no_space']));

        $this->assertTrue($this->adapter->hasForeignKey('table', ['ref_lots_of_space', 'ref_lots_of_space']));
        $this->adapter->dropForeignKey('table', ['ref_lots_of_space', 'ref_lots_of_space']);
        $this->assertFalse($this->adapter->hasForeignKey('table', ['ref_lots_of_space', 'ref_lots_of_space']));
    }

    public function testDropForeignKeyWithMultipleColumns()
    {
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable
            ->addColumn('field1', 'string')
            ->addColumn('field2', 'string')
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->addIndex(['field1', 'id'], ['unique' => true])
            ->addIndex(['id', 'field1', 'field2'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addColumn('ref_table_field1', 'string')
            ->addColumn('ref_table_field2', 'string')
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
            ->addColumn('field1', 'string')
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addColumn('ref_table_field1', 'string')
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
            ->addColumn('field1', 'string')
            ->addIndex(['id', 'field1'], ['unique' => true])
            ->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addColumn('ref_table_field1', 'string')
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

        $table = new Table('another_table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), ['REF_TABLE_ID']);
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
    }

    public function testDropForeignKeyByName()
    {
        $this->expectExceptionMessage('SQLite does not have named foreign keys');
        $this->expectException(BadMethodCallException::class);

        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer', ['signed' => false])
            ->addForeignKeyWithName('my_constraint', ['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $this->adapter->dropForeignKey($table->getName(), [], 'my_constraint');
    }

    public function testHasDatabase()
    {
        if (SQLITE_DB_CONFIG['name'] === ':memory:') {
            $this->markTestSkipped('Skipping hasDatabase() when testing in-memory db.');
        }
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(SQLITE_DB_CONFIG['name']));
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

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');

        foreach ($rows as $row) {
            if ($row['tbl_name'] === 'table1') {
                $sql = $row['sql'];
            }
        }

        $this->assertMatchesRegularExpression('/\/\* Comments from "column1" \*\//', $sql);
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $table2 = new Table('table2', [], $this->adapter);
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
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer', ['null' => true])
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
        $this->assertNull($rows[3]['column2']);
    }

    public function testInsertData()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer', ['null' => true])
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
        $this->assertNull($rows[3]['column2']);
    }

    public function testBulkInsertDataEnum()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'string', ['null' => true])
            ->addColumn('column3', 'string', ['default' => 'c'])
            ->insert([
                'column1' => 'a',
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');

        $this->assertEquals('a', $rows[0]['column1']);
        $this->assertNull($rows[0]['column2']);
        $this->assertEquals('c', $rows[0]['column3']);
    }

    public function testNullWithoutDefaultValue()
    {
        $this->markTestSkipped('Skipping for now. See Github Issue #265.');

        // construct table with default/null combinations
        $table = new Table('table1', [], $this->adapter);
        $table->addColumn('aa', 'string', ['null' => true]) // no default value
        ->addColumn('bb', 'string', ['null' => false]) // no default value
        ->addColumn('cc', 'string', ['null' => true, 'default' => 'some1'])
            ->addColumn('dd', 'string', ['null' => false, 'default' => 'some2'])
            ->save();

        // load table info
        $columns = $this->adapter->getColumns('table1');

        $this->assertCount(5, $columns);

        $aa = $columns[1];
        $bb = $columns[2];
        $cc = $columns[3];
        $dd = $columns[4];

        $this->assertEquals('aa', $aa->getName());
        $this->assertTrue($aa->isNull());
        $this->assertNull($aa->getDefault());

        $this->assertEquals('bb', $bb->getName());
        $this->assertFalse($bb->isNull());
        $this->assertNull($bb->getDefault());

        $this->assertEquals('cc', $cc->getName());
        $this->assertTrue($cc->isNull());
        $this->assertEquals('some1', $cc->getDefault());

        $this->assertEquals('dd', $dd->getName());
        $this->assertFalse($dd->isNull());
        $this->assertEquals('some2', $dd->getDefault());
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
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, `column1` VARCHAR NOT NULL, `column2` INTEGER NULL, `column3` VARCHAR NULL DEFAULT 'test');
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
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option doesn\'t dump the insert to the output');

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

        $expectedOutput = 'C';

        $table = new Table('table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1,
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE `table1` (`column1` VARCHAR NOT NULL, `column2` INTEGER NULL, PRIMARY KEY (`column1`));
INSERT INTO `table1` (`column1`, `column2`) VALUES ('id1', 1);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $actualOutput = preg_replace("/\r\n|\r/", "\n", $actualOutput); // normalize line endings for Windows
        $this->assertStringContainsString($expectedOutput, $actualOutput, 'Passing the --dry-run option does not dump create and then insert table queries to the output');
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

        $this->assertEquals(0, $stm->rowCount());
        $this->assertEquals(
            ['id' => 2, 'string_col' => 'value2', 'int_col' => '2'],
            $stm->fetch('assoc')
        );

        $builder = $this->adapter->getQueryBuilder(Query::TYPE_DELETE);
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

    /**
     * Tests adding more than one column to a table
     * that already exists due to adapters having different add column instructions
     */
    public function testAlterTableColumnAdd()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->create();

        $table->addColumn('string_col', 'string', ['default' => '']);
        $table->addColumn('string_col_2', 'string', ['null' => true]);
        $table->addColumn('string_col_3', 'string', ['null' => false]);
        $table->addTimestamps();
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        $expected = [
            ['name' => 'id', 'type' => 'integer', 'default' => null, 'null' => false],
            ['name' => 'string_col', 'type' => 'string', 'default' => '', 'null' => true],
            ['name' => 'string_col_2', 'type' => 'string', 'default' => null, 'null' => true],
            ['name' => 'string_col_3', 'type' => 'string', 'default' => null, 'null' => false],
            ['name' => 'created_at', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'null' => false],
            ['name' => 'updated_at', 'type' => 'timestamp', 'default' => null, 'null' => true],
        ];

        $this->assertEquals(count($expected), count($columns));

        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertSame($expected[$i]['name'], $columns[$i]->getName(), "Wrong name for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['type'], $columns[$i]->getType(), "Wrong type for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['default'], $columns[$i]->getDefault() instanceof Literal ? (string)$columns[$i]->getDefault() : $columns[$i]->getDefault(), "Wrong default for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['null'], $columns[$i]->getNull(), "Wrong null for {$expected[$i]['name']}");
        }
    }

    public function testAlterTableWithConstraints()
    {
        $table = new Table('table1', [], $this->adapter);
        $table->create();

        $table2 = new Table('table2', [], $this->adapter);
        $table2->create();

        $table
            ->addColumn('table2_id', 'integer', ['null' => false])
            ->addForeignKey('table2_id', 'table2', 'id', [
                'delete' => 'SET NULL',
            ]);
        $table->update();

        $table->addColumn('column3', 'string', ['default' => null, 'null' => true]);
        $table->update();

        $columns = $this->adapter->getColumns('table1');
        $expected = [
            ['name' => 'id', 'type' => 'integer', 'default' => null, 'null' => false],
            ['name' => 'table2_id', 'type' => 'integer', 'default' => null, 'null' => false],
            ['name' => 'column3', 'type' => 'string', 'default' => null, 'null' => true],
        ];

        $this->assertEquals(count($expected), count($columns));

        $columnCount = count($columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $this->assertSame($expected[$i]['name'], $columns[$i]->getName(), "Wrong name for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['type'], $columns[$i]->getType(), "Wrong type for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['default'], $columns[$i]->getDefault() instanceof Literal ? (string)$columns[$i]->getDefault() : $columns[$i]->getDefault(), "Wrong default for {$expected[$i]['name']}");
            $this->assertSame($expected[$i]['null'], $columns[$i]->getNull(), "Wrong null for {$expected[$i]['name']}");
        }
    }

    /**
     * Tests that operations that trigger implicit table drops will not cause
     * a foreign key constraint violation error.
     */
    public function testAlterTableDoesNotViolateRestrictedForeignKeyConstraint()
    {
        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $articlesTable = new Table('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $this->adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey('comments', ['article_id']));

        $articlesTable
            ->addColumn('new_column', 'integer')
            ->update();

        $articlesTable
            ->renameColumn('new_column', 'new_column_renamed')
            ->update();

        $articlesTable
            ->changeColumn('new_column_renamed', 'integer', [
                'default' => 1,
            ])
            ->update();

        $articlesTable
            ->removeColumn('new_column_renamed')
            ->update();

        $articlesTable
            ->addIndex('id', ['name' => 'ID_IDX'])
            ->update();

        $articlesTable
            ->removeIndex('id')
            ->update();

        $articlesTable
            ->addForeignKey('id', 'comments', 'id')
            ->update();

        $articlesTable
            ->dropForeignKey('id')
            ->update();

        $articlesTable
            ->addColumn('id2', 'integer')
            ->addIndex('id', ['unique' => true])
            ->changePrimaryKey('id2')
            ->update();
    }

    /**
     * Tests that foreign key constraint violations introduced around the table
     * alteration process (being it implicitly by the process itself or by the user)
     * will trigger an error accordingly.
     */
    public function testAlterTableDoesViolateForeignKeyConstraintOnTargetTableChange()
    {
        $articlesTable = new Table('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $this->adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey('comments', ['article_id']));

        $this->adapter->execute('PRAGMA foreign_keys = OFF');
        $this->adapter->execute('DELETE FROM articles');
        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation: FOREIGN KEY constraint on `comments` failed.');

        $articlesTable
            ->addColumn('new_column', 'integer')
            ->update();
    }

    /**
     * Tests that foreign key constraint violations introduced around the table
     * alteration process (being it implicitly by the process itself or by the user)
     * will trigger an error accordingly.
     */
    public function testAlterTableDoesViolateForeignKeyConstraintOnSourceTableChange()
    {
        $adapter = $this
            ->getMockBuilder(SQLiteAdapter::class)
            ->setConstructorArgs([SQLITE_DB_CONFIG, new ArrayInput([]), new NullOutput()])
            ->onlyMethods(['query'])
            ->getMock();

        $adapterReflection = new ReflectionObject($adapter);
        $queryReflection = $adapterReflection->getParentClass()->getMethod('query');

        $adapter
            ->expects($this->atLeastOnce())
            ->method('query')
            ->willReturnCallback(function (string $sql, array $params = []) use ($adapter, $queryReflection) {
                if ($sql === 'PRAGMA foreign_key_check(`comments`)') {
                    $adapter->execute('PRAGMA foreign_keys = OFF');
                    $adapter->execute('DELETE FROM articles');
                    $adapter->execute('PRAGMA foreign_keys = ON');
                }

                return $queryReflection->invoke($adapter, $sql, $params);
            });

        $articlesTable = new Table('articles', [], $adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($adapter->hasForeignKey('comments', ['article_id']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation: FOREIGN KEY constraint on `comments` failed.');

        $commentsTable
            ->addColumn('new_column', 'integer')
            ->update();
    }

    /**
     * Tests that the adapter's foreign key validation does not apply when
     * the `foreign_keys` pragma is set to `OFF`.
     */
    public function testAlterTableForeignKeyConstraintValidationNotRunningWithDisabledForeignKeys()
    {
        $articlesTable = new Table('articles', [], $this->adapter);
        $articlesTable
            ->insert(['id' => 1])
            ->save();

        $commentsTable = new Table('comments', [], $this->adapter);
        $commentsTable
            ->addColumn('article_id', 'integer')
            ->addForeignKey('article_id', 'articles', 'id', [
                'update' => ForeignKey::RESTRICT,
                'delete' => ForeignKey::RESTRICT,
            ])
            ->insert(['id' => 1, 'article_id' => 1])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey('comments', ['article_id']));

        $this->adapter->execute('PRAGMA foreign_keys = OFF');
        $this->adapter->execute('DELETE FROM articles');

        $noException = false;
        try {
            $articlesTable
                ->addColumn('new_column1', 'integer')
                ->update();

            $noException = true;
        } finally {
            $this->assertTrue($noException);
        }

        $this->adapter->execute('PRAGMA foreign_keys = ON');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation: FOREIGN KEY constraint on `comments` failed.');

        $articlesTable
            ->addColumn('new_column2', 'integer')
            ->update();
    }

    public function testLiteralSupport()
    {
        $createQuery = <<<'INPUT'
CREATE TABLE `test` (`real_col` DECIMAL)
INPUT;
        $this->adapter->execute($createQuery);
        $table = new Table('test', [], $this->adapter);
        $columns = $table->getColumns();
        $this->assertCount(1, $columns);
        $this->assertEquals(Literal::from('decimal'), array_pop($columns)->getType());
    }

    /**
     * @dataProvider provideTableNamesForPresenceCheck
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

    /**
     * @dataProvider provideIndexColumnsToCheck
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

    /**
     * @dataProvider provideIndexNamesToCheck
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

    /**
     * @dataProvider providePrimaryKeysToCheck
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
            ['create table not_t(a integer)', 'a', false], // test checks table t which does not exist
        ];
    }

    /**
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::hasPrimaryKey
     */
    public function testHasNamedPrimaryKey()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->adapter->hasPrimaryKey('t', [], 'named_constraint');
    }

    /**
     * @dataProvider provideForeignKeysToCheck
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
            ['create table t(a integer references other(b))', ['a', 'a'], false],
            ['create table t(a integer, foreign key(a) references other(a))', 'a', true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', 'a', false],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['a', 'b'], true],
            ['create table t(a integer, b integer, foreign key(a,b) references other(a,b))', ['b', 'a'], false],
            ['create table t(a integer, "B" integer, foreign key(a,"B") references other(a,b))', ['a', 'b'], true],
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
        $refTable = new Table('tbl_parent_1', [], $this->adapter);
        $refTable->addColumn('column', 'string')->create();

        $refTable = new Table('tbl_parent_2', [], $this->adapter);
        $refTable->create();

        $refTable = new Table('tbl_parent_3', [
            'id' => false,
            'primary_key' => ['id', 'column'],
        ], $this->adapter);
        $refTable->addColumn('id', 'integer')->addColumn('column', 'string')->create();

        // use raw sql instead of table builder so that we can have check constraints
        $this->adapter->execute("
        CREATE TABLE `tbl_child` (
            `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `column` VARCHAR NOT NULL, `parent_1_id` INTEGER NOT NULL,
            `parent_2_id` INTEGER NOT NULL,
            `parent_3_id` INTEGER NOT NULL,
            CONSTRAINT `fk_parent_1_id` FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            CONSTRAINT [fk_[_brackets] FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            CONSTRAINT `fk_``_ticks` FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            CONSTRAINT \"fk_\"\"_double_quotes\" FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            CONSTRAINT 'fk_''_single_quotes' FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            CONSTRAINT fk_no_quotes FOREIGN KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            CONSTRAINT`fk_no_space`FOREIGN KEY(`parent_1_id`)REFERENCES`tbl_parent_1`(`id`),
            constraint
                `fk_lots_of_space`    FOReign		KEY (`parent_1_id`) REFERENCES `tbl_parent_1` (`id`),
            FOREIGN KEY (`parent_2_id`) REFERENCES `tbl_parent_2` (`id`),
            CONSTRAINT `check_constraint_1` CHECK (column<>'world'),
            CONSTRAINT `fk_composite_key` FOREIGN KEY (`parent_3_id`,`column`) REFERENCES `tbl_parent_3` (`id`,`column`)
            CONSTRAINT `check_constraint_2` CHECK (column<>'hello')
        )");

        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_parent_1_id'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_[_brackets'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_`_ticks'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_"_double_quotes'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], "fk_'_single_quotes"));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_no_quotes'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_no_space'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_lots_of_space'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['parent_1_id']));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['parent_2_id']));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', [], 'fk_composite_key'));
        $this->assertTrue($this->adapter->hasForeignKey('tbl_child', ['parent_3_id', 'column']));
        $this->assertFalse($this->adapter->hasForeignKey('tbl_child', [], 'check_constraint_1'));
        $this->assertFalse($this->adapter->hasForeignKey('tbl_child', [], 'check_constraint_2'));
    }

    /**
     * @dataProvider providePhinxTypes
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getSqlType
     */
    public function testGetSqlType($phinxType, $limit, $exp)
    {
        if ($exp instanceof Exception) {
            $this->expectException(get_class($exp));

            $this->adapter->getSqlType($phinxType, $limit);
        } else {
            $exp = ['name' => $exp, 'limit' => $limit];
            $this->assertEquals($exp, $this->adapter->getSqlType($phinxType, $limit));
        }
    }

    public function providePhinxTypes()
    {
        $unsupported = new UnsupportedColumnTypeException();

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
            [SQLiteAdapter::PHINX_TYPE_DECIMAL, null, SQLiteAdapter::PHINX_TYPE_DECIMAL],
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

    /**
     * @dataProvider provideSqlTypes
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::getPhinxType
     */
    public function testGetPhinxType($sqlType, $exp)
    {
        $this->assertEquals($exp, $this->adapter->getPhinxType($sqlType));
    }

    /**
     * @return array
     */
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
            ['tinyint', ['name' => SQLiteAdapter::PHINX_TYPE_TINY_INTEGER, 'limit' => null, 'scale' => null]],
            ['tinyint(1)', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['tinyinteger', ['name' => SQLiteAdapter::PHINX_TYPE_TINY_INTEGER, 'limit' => null, 'scale' => null]],
            ['tinyinteger(1)', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
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
            ['TINYINT', ['name' => SQLiteAdapter::PHINX_TYPE_TINY_INTEGER, 'limit' => null, 'scale' => null]],
            ['TINYINT(1)', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
            ['TINYINTEGER', ['name' => SQLiteAdapter::PHINX_TYPE_TINY_INTEGER, 'limit' => null, 'scale' => null]],
            ['TINYINTEGER(1)', ['name' => SQLiteAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'scale' => null]],
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
        $columnTypes = $this->adapter->getColumnTypes();
        $expected = [
            SQLiteAdapter::PHINX_TYPE_BIG_INTEGER,
            SQLiteAdapter::PHINX_TYPE_BINARY,
            SQLiteAdapter::PHINX_TYPE_BLOB,
            SQLiteAdapter::PHINX_TYPE_BOOLEAN,
            SQLiteAdapter::PHINX_TYPE_CHAR,
            SQLiteAdapter::PHINX_TYPE_DATE,
            SQLiteAdapter::PHINX_TYPE_DATETIME,
            SQLiteAdapter::PHINX_TYPE_DECIMAL,
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
            SQLiteAdapter::PHINX_TYPE_BINARYUUID,
            SQLiteAdapter::PHINX_TYPE_TIMESTAMP,
            SQLiteAdapter::PHINX_TYPE_TINY_INTEGER,
            SQLiteAdapter::PHINX_TYPE_VARBINARY,
        ];
        sort($columnTypes);
        sort($expected);

        $this->assertEquals($expected, $columnTypes);
    }

    /**
     * @dataProvider provideColumnTypesForValidation
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
            [SQLiteAdapter::PHINX_TYPE_DECIMAL, true],
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

    /**
     * @dataProvider provideDatabaseVersionStrings
     * @covers \Phinx\Db\Adapter\SQLiteAdapter::databaseVersionAtLeast
     */
    public function testDatabaseVersionAtLeast($ver, $exp)
    {
        $this->assertSame($exp, $this->adapter->databaseVersionAtLeast($ver));
    }

    public function provideDatabaseVersionStrings()
    {
        return [
            ['2', true],
            ['3', true],
            ['4', false],
            ['3.0', true],
            ['3.0.0.0.0.0', true],
            ['3.0.0.0.0.99999', true],
            ['3.9999', false],
        ];
    }

    /**
     * @dataProvider provideColumnNamesToCheck
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

    /**
     * @dataProvider provideIdentityCandidates
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

    /**
     * @dataProvider provideDefaultValues
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
            'Comment 1' => ['create table t(a integer default ( /* this is perfectly fine */ null ))', null],
            'Comment 2' => ["create table t(a integer default ( /* this\nis\nperfectly\nfine */ null ))", null],
            'Line comment 1' => ["create table t(a integer default ( -- this is perfectly fine, too\n null ))", null],
            'Line comment 2' => ["create table t(a integer default ( -- this is perfectly fine, too\r\n null ))", null],
            'Current date LC' => ['create table t(a date default current_date)', 'CURRENT_DATE'],
            'Current date UC' => ['create table t(a date default CURRENT_DATE)', 'CURRENT_DATE'],
            'Current date MC' => ['create table t(a date default CURRENT_date)', 'CURRENT_DATE'],
            'Current time LC' => ['create table t(a time default current_time)', 'CURRENT_TIME'],
            'Current time UC' => ['create table t(a time default CURRENT_TIME)', 'CURRENT_TIME'],
            'Current time MC' => ['create table t(a time default CURRENT_time)', 'CURRENT_TIME'],
            'Current timestamp LC' => ['create table t(a datetime default current_timestamp)', 'CURRENT_TIMESTAMP'],
            'Current timestamp UC' => ['create table t(a datetime default CURRENT_TIMESTAMP)', 'CURRENT_TIMESTAMP'],
            'Current timestamp MC' => ['create table t(a datetime default CURRENT_timestamp)', 'CURRENT_TIMESTAMP'],
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

    /**
     * @dataProvider provideBooleanDefaultValues
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

    /**
     * @dataProvider provideTablesForTruncation
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

    /**
     * @return array
     */
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
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRename, 'string')->save();

        $table = new Table('table', [], $this->adapter);
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
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangeColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToChange = 'columnToChange';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToChange, 'string')->save();

        $table = new Table('table', [], $this->adapter);
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
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterRemoveColumn()
    {
        $refTableColumnId = 'ref_table_id';
        $refTableColumnToRemove = 'columnToRemove';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnToRemove, 'string')->save();

        $table = new Table('table', [], $this->adapter);
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
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterChangePrimaryKey()
    {
        $refTableColumnAdditionalId = 'additional_id';
        $refTableColumnId = 'ref_table_id';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableColumnAdditionalId, 'integer')->save();

        $table = new Table('table', [], $this->adapter);
        $table->addColumn($refTableColumnId, 'integer');
        $table->addForeignKey($refTableColumnId, $refTable->getName(), 'id');
        $table->save();

        $refTable
            ->addIndex('id', ['unique' => true])
            ->changePrimaryKey($refTableColumnAdditionalId)
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), [$refTableColumnId]));
        $this->assertFalse($this->adapter->hasTable("tmp_{$refTable->getName()}"));
        $this->assertTrue($this->adapter->getColumns($refTable->getName())[1]->getIdentity());

        $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');
        foreach ($rows as $row) {
            if ($row['tbl_name'] === $table->getName()) {
                $sql = $row['sql'];
            }
        }
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testForeignKeyReferenceCorrectAfterDropForeignKey()
    {
        $refTableAdditionalColumnId = 'ref_table_additional_id';
        $refTableAdditional = new Table('ref_table_additional', [], $this->adapter);
        $refTableAdditional->save();

        $refTableColumnId = 'ref_table_id';
        $refTable = new Table('ref_table', [], $this->adapter);
        $refTable->addColumn($refTableAdditionalColumnId, 'integer');
        $refTable->addForeignKey($refTableAdditionalColumnId, $refTableAdditional->getName(), 'id');
        $refTable->save();

        $table = new Table('table', [], $this->adapter);
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
        $this->assertStringContainsString("REFERENCES `{$refTable->getName()}` (`id`)", $sql);
    }

    public function testInvalidPdoAttribute()
    {
        $adapter = new SQLiteAdapter(SQLITE_DB_CONFIG + ['attr_invalid' => true]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid PDO attribute: attr_invalid (\PDO::ATTR_INVALID)');
        $adapter->connect();
    }

    public function testPdoExceptionUpdateNonExistingTable()
    {
        $this->expectException(PDOException::class);
        $table = new Table('non_existing_table', [], $this->adapter);
        $table->addColumn('column', 'string')->update();
    }

    public function testPdoPersistentConnection()
    {
        $adapter = new SQLiteAdapter(SQLITE_DB_CONFIG + ['attr_persistent' => true]);
        $this->assertTrue($adapter->getConnection()->getAttribute(PDO::ATTR_PERSISTENT));
    }

    public function testPdoNotPersistentConnection()
    {
        $adapter = new SQLiteAdapter(SQLITE_DB_CONFIG);
        $this->assertFalse($adapter->getConnection()->getAttribute(PDO::ATTR_PERSISTENT));
    }
}
