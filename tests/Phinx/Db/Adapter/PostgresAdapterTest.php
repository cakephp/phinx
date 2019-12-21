<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\PostgresAdapter;
use Phinx\Db\Adapter\UnsupportedColumnTypeException;
use Phinx\Db\Table\Column;
use Phinx\Util\Literal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

class PostgresAdapterTest extends TestCase
{
    /**
     * Check if Postgres is enabled in the current PHP
     *
     * @return bool
     */
    private static function isPostgresAvailable()
    {
        static $available;

        if (is_null($available)) {
            $available = in_array('pgsql', \PDO::getAvailableDrivers());
        }

        return $available;
    }

    /**
     * @var \Phinx\Db\Adapter\PostgresAdapter
     */
    private $adapter;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_POSTGRES_ENABLED) {
            $this->markTestSkipped('Postgres tests disabled.  See TESTS_PHINX_DB_ADAPTER_POSTGRES_ENABLED constant.');
        }

        if (!self::isPostgresAvailable()) {
            $this->markTestSkipped('Postgres is not available.  Please install php-pdo-pgsql or equivalent package.');
        }

        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_POSTGRES_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_POSTGRES_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PORT,
            'schema' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE_SCHEMA
        ];
        $this->adapter = new PostgresAdapter($options, new ArrayInput([]), new NullOutput());

        $this->adapter->dropAllSchemas();
        $this->adapter->createSchema($options['schema']);

        $citext = $this->adapter->fetchRow("SELECT COUNT(*) AS enabled FROM pg_extension WHERE extname = 'citext'");
        if (!$citext['enabled']) {
            $this->adapter->query('CREATE EXTENSION IF NOT EXISTS citext');
        }

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    public function tearDown()
    {
        if ($this->adapter) {
            $this->adapter->dropAllSchemas();
            unset($this->adapter);
        }
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
            'host' => TESTS_PHINX_DB_ADAPTER_POSTGRES_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        ];

        try {
            $adapter = new PostgresAdapter($options, new ArrayInput([]), new NullOutput());
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

    public function testQuoteSchemaName()
    {
        $this->assertEquals('"schema"', $this->adapter->quoteSchemaName('schema'));
        $this->assertEquals('"schema.schema"', $this->adapter->quoteSchemaName('schema.schema'));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('"public"."table"', $this->adapter->quoteTableName('table'));
        $this->assertEquals('"table"."table"', $this->adapter->quoteTableName('table.table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('"string"', $this->adapter->quoteColumnName('string'));
        $this->assertEquals('"string.string"', $this->adapter->quoteColumnName('string.string'));
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

    public function testCreateTableWithSchema()
    {
        $this->adapter->createSchema('nschema');

        $table = new \Phinx\Db\Table('nschema.ntable', [], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('nschema.ntable'));
        $this->assertTrue($this->adapter->hasColumn('nschema.ntable', 'id'));
        $this->assertTrue($this->adapter->hasColumn('nschema.ntable', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('nschema.ntable', 'email'));
        $this->assertFalse($this->adapter->hasColumn('nschema.ntable', 'address'));

        $this->adapter->dropSchema('nschema');
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
        $this->assertTrue($this->adapter->hasIndex('table1', ['tag_id', 'user_id']));
        $this->assertFalse($this->adapter->hasIndex('table1', ['tag_id', 'user_email']));
    }

    public function testCreateTableWithMultiplePrimaryKeysWithSchema()
    {
        $this->adapter->createSchema('schema1');

        $options = [
            'id' => false,
            'primary_key' => ['user_id', 'tag_id']
        ];
        $table = new \Phinx\Db\Table('schema1.table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasIndex('schema1.table1', ['user_id', 'tag_id']));
        $this->assertTrue($this->adapter->hasIndex('schema1.table1', ['tag_id', 'user_id']));
        $this->assertFalse($this->adapter->hasIndex('schema1.table1', ['tag_id', 'user_email']));

        $this->adapter->dropSchema('schema1');
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

    public function testAddPrimaryKey()
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
                "SELECT description
                    FROM pg_description
                    JOIN pg_class ON pg_description.objoid = pg_class.oid
                    WHERE relname = '%s'",
                'table1'
            )
        );
        $this->assertEquals('comment1', $rows[0]['description']);
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
                "SELECT description
                    FROM pg_description
                    JOIN pg_class ON pg_description.objoid = pg_class.oid
                    WHERE relname = '%s'",
                'table1'
            )
        );
        $this->assertEquals('comment2', $rows[0]['description']);
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
                "SELECT description
                    FROM pg_description
                    JOIN pg_class ON pg_description.objoid = pg_class.oid
                    WHERE relname = '%s'",
                'table1'
            )
        );
        $this->assertEmpty($rows);
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

    public function testRenameTableWithSchema()
    {
        $this->adapter->createSchema('schema1');

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('schema1.table1'));
        $this->assertFalse($this->adapter->hasTable('schema1.table2'));
        $this->adapter->renameTable('schema1.table1', 'table2');
        $this->assertFalse($this->adapter->hasTable('schema1.table1'));
        $this->assertTrue($this->adapter->hasTable('schema1.table2'));

        $this->adapter->dropSchema('schema1');
    }

    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
              ->save();
        $this->assertTrue($table->hasColumn('email'));
    }

    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'string', ['default' => 'test'])
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
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_zero') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('0', $column->getDefault());
            }
        }
    }

    public function testAddColumnWithDefaultBoolean()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_true', 'boolean', ['default' => true])
              ->addColumn('default_false', 'boolean', ['default' => false])
              ->addColumn('default_null', 'boolean', ['default' => null, 'null' => true])
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_true') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('true', $column->getDefault());
            }
            if ($column->getName() == 'default_false') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('false', $column->getDefault());
            }
            if ($column->getName() == 'default_null') {
                $this->assertNull($column->getDefault());
            }
        }
    }

    public function testAddColumnWithBooleanIgnoreLimitCastDefault()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('limit_bool_true', 'boolean', [
            'default' => 1,
            'limit' => 1,
            'null' => false,
        ]);
        $table->addColumn('limit_bool_false', 'boolean', [
            'default' => 0,
            'limit' => 0,
            'null' => false,
        ]);
        $table->save();

        $columns = $this->adapter->getColumns('table1');
        $this->assertCount(3, $columns);
        /**
         * @var Column $column
         */
        $column = $columns[1];
        $this->assertSame('limit_bool_true', $column->getName());
        $this->assertNotNull($column->getDefault());
        $this->assertSame('true', $column->getDefault());
        $this->assertNull($column->getLimit());

        $column = $columns[2];
        $this->assertSame('limit_bool_false', $column->getName());
        $this->assertNotNull($column->getDefault());
        $this->assertSame('false', $column->getDefault());
        $this->assertNull($column->getLimit());
    }

    public function testAddColumnWithDefaultLiteral()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_ts', 'timestamp', ['default' => Literal::from('now()')])
              ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'default_ts') {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('now()', (string)$column->getDefault());
            }
        }
    }

    public function testAddColumnWithLiteralType()
    {
        $table = new \Phinx\Db\Table('citable', ['id' => false], $this->adapter);
        $table
            ->addColumn('insensitive', Literal::from('citext'))
            ->save();

        $this->assertTrue($this->adapter->hasColumn('citable', 'insensitive'));

        /** @var $columns Column[] */
        $columns = $this->adapter->getColumns('citable');
        foreach ($columns as $column) {
            if ($column->getName() === 'insensitive') {
                $this->assertEquals(
                    'citext',
                    (string)$column->getType(),
                    'column: ' . $column->getName()
                );
            }
        }
    }

    public function testAddColumnWithComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();

        $this->assertFalse($table->hasColumn('email'));

        $table->addColumn('email', 'string', ['comment' => $comment = 'Comments from column "email"'])
              ->save();

        $this->assertTrue($table->hasColumn('email'));

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'email\''
        );

        $this->assertEquals(
            $comment,
            $row['column_comment'],
            'The column comment was not set when you used addColumn()'
        );
    }

    public function testAddDecimalWithPrecisionAndScale()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('number', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('number2', 'decimal', ['limit' => 12])
            ->addColumn('number3', 'decimal')
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'number') {
                $this->assertEquals("10", $column->getPrecision());
                $this->assertEquals("2", $column->getScale());
            }

            if ($column->getName() == 'number2') {
                $this->assertEquals("12", $column->getPrecision());
                $this->assertEquals("0", $column->getScale());
            }
        }
    }

    public function testAddTimestampWithPrecision()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $table->addColumn('timestamp1', 'timestamp', ['precision' => 0])
            ->addColumn('timestamp2', 'timestamp', ['precision' => 4])
            ->addColumn('timestamp3', 'timestamp')
            ->save();
        $columns = $this->adapter->getColumns('table1');
        foreach ($columns as $column) {
            if ($column->getName() == 'timestamp1') {
                $this->assertEquals("0", $column->getPrecision());
            }

            if ($column->getName() == 'timestamp2') {
                $this->assertEquals("4", $column->getPrecision());
            }

            if ($column->getName() == 'timestamp3') {
                $this->assertEquals("6", $column->getPrecision());
            }
        }
    }

    public function providerArrayType()
    {
        return [
            ['array_text', 'text[]'],
            ['array_char', 'char[]'],
            ['array_integer', 'integer[]'],
            ['array_float', 'float[]'],
            ['array_decimal', 'decimal[]'],
            ['array_timestamp', 'timestamp[]'],
            ['array_time', 'time[]'],
            ['array_date', 'date[]'],
            ['array_boolean', 'boolean[]'],
            ['array_json', 'json[]'],
            ['array_json2d', 'json[][]'],
            ['array_json3d', 'json[][][]'],
            ['array_uuid', 'uuid[]'],
            ['array_interval', 'interval[]'],
        ];
    }

    /**
     * @dataProvider providerArrayType
     */
    public function testAddColumnArrayType($column_name, $column_type)
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn($column_name));
        $table->addColumn($column_name, $column_type)
            ->save();
        $this->assertTrue($table->hasColumn($column_name));
    }

    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('t', 'column2'));
        $this->adapter->renameColumn('t', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('t', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('t', 'column2'));
    }

    public function testRenameColumnIsCaseSensitive()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('columnOne', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'columnOne'));
        $this->assertFalse($this->adapter->hasColumn('t', 'columnTwo'));
        $this->adapter->renameColumn('t', 'columnOne', 'columnTwo');
        $this->assertFalse($this->adapter->hasColumn('t', 'columnOne'));
        $this->assertTrue($this->adapter->hasColumn('t', 'columnTwo'));
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
            $this->assertEquals('The specified column does not exist: column2', $e->getMessage());
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

    public function testChangeColumnWithDefault()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setName('column1')
                   ->setType('string')
                   ->setNull(true);

        $newColumn1->setDefault('Test');
        $table->changeColumn('column1', $newColumn1)->save();

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->isNull());
                $this->assertRegExp('/Test/', $column->getDefault());
            }
        }
    }

    public function testChangeColumnWithDropDefault()
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'Test'])
              ->save();

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertRegExp('/Test/', $column->getDefault());
            }
        }

        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setName('column1')
                   ->setType('string');

        $table->changeColumn('column1', $newColumn1)->save();

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertNull($column->getDefault());
            }
        }
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
            ['column2_1', 'integer', []],
            ['column3', 'biginteger', []],
            ['column4', 'text', []],
            ['column5', 'float', []],
            ['column6', 'decimal', []],
            ['column7', 'datetime', []],
            ['column8', 'time', []],
            ['column9', 'timestamp', [], 'datetime'],
            ['column10', 'date', []],
            ['column11', 'binary', []],
            ['column12', 'boolean', []],
            ['column13', 'string', ['limit' => 10]],
            ['column16', 'interval', []],
            ['decimal_precision_scale', 'decimal', ['precision' => 10, 'scale' => 2]],
            ['decimal_limit', 'decimal', ['limit' => 10]],
            ['decimal_precision', 'decimal', ['precision' => 10]],
        ];
    }

    /**
     * @dataProvider columnsProvider
     */
    public function testGetColumns($colName, $type, $options, $actualType = null)
    {
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());

        if (!$actualType) {
            $actualType = $type;
        }

        if (is_string($columns[1]->getType())) {
            $this->assertEquals($actualType, $columns[1]->getType());
        } else {
            $this->assertEquals(['name' => $actualType] + $options, $columns[1]->getType());
        }
    }

    /**
     * @dataProvider columnsProvider
     */
    public function testGetColumnsWithSchema($colName, $type, $options, $actualType = null)
    {
        $this->adapter->createSchema('tschema');

        $table = new \Phinx\Db\Table('tschema.t', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('tschema.t');
        $this->assertCount(2, $columns);
        $this->assertEquals($colName, $columns[1]->getName());

        if (!$actualType) {
            $actualType = $type;
        }

        if (is_string($columns[1]->getType())) {
            $this->assertEquals($actualType, $columns[1]->getType());
        } else {
            $this->assertEquals(['name' => $actualType] + $options, $columns[1]->getType());
        }

        $this->adapter->dropSchema('tschema');
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

    public function testAddIndexWithSchema()
    {
        $this->adapter->createSchema('schema1');

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));

        $this->adapter->dropSchema('schema1');
    }

    public function testAddIndexWithNameWithSchema()
    {
        $this->adapter->createSchema('schema1');

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email', ['name' => 'indexEmail'])
            ->save();
        $this->assertTrue($table->hasIndex('email'));

        $this->adapter->dropSchema('schema1');
    }

    public function testAddIndexIsCaseSensitive()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('theEmail', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('theEmail'));
        $table->addIndex('theEmail')
            ->save();
        $this->assertTrue($table->hasIndex('theEmail'));
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

        // index with name specified, but dropping it by column name
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

    public function testDropIndexWithSchema()
    {
        $this->adapter->createSchema('schema1');

        // single column index
        $table = new \Phinx\Db\Table('schema1.table5', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email')
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndex($table->getName(), 'email');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('schema1.table6', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'])
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table2->getName(), ['fname', 'lname']);
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        // index with name specified, but dropping it by column name
        $table3 = new \Phinx\Db\Table('schema1.table7', [], $this->adapter);
        $table3->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'someIndexName'])
            ->save();
        $this->assertTrue($table3->hasIndex('email'));
        $this->adapter->dropIndex($table3->getName(), 'email');
        $this->assertFalse($table3->hasIndex('email'));

        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('schema1.table8', [], $this->adapter);
        $table4->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(['fname', 'lname'], ['name' => 'multiname'])
            ->save();
        $this->assertTrue($table4->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndex($table4->getName(), ['fname', 'lname']);
        $this->assertFalse($table4->hasIndex(['fname', 'lname']));

        $this->adapter->dropSchema('schema1');
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
               ->addIndex(
                   ['fname', 'lname'],
                   ['name' => 'twocolumnuniqueindex', 'unique' => true]
               )
               ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnuniqueindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));
    }

    public function testDropIndexByNameWithSchema()
    {
        $this->adapter->createSchema('schema1');

        // single column index
        $table = new \Phinx\Db\Table('schema1.Table1', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->addIndex('email', ['name' => 'myemailIndex'])
            ->save();
        $this->assertTrue($table->hasIndex('email'));
        $this->adapter->dropIndexByName($table->getName(), 'myemailIndex');
        $this->assertFalse($table->hasIndex('email'));

        // multiple column index
        $table2 = new \Phinx\Db\Table('schema1.table2', [], $this->adapter);
        $table2->addColumn('fname', 'string')
            ->addColumn('lname', 'string')
            ->addIndex(
                ['fname', 'lname'],
                ['name' => 'twocolumnuniqueindex', 'unique' => true]
            )
            ->save();
        $this->assertTrue($table2->hasIndex(['fname', 'lname']));
        $this->adapter->dropIndexByName($table2->getName(), 'twocolumnuniqueindex');
        $this->assertFalse($table2->hasIndex(['fname', 'lname']));

        $this->adapter->dropSchema('schema1');
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

    public function testAddForeignKeyWithSchema()
    {
        $this->adapter->createSchema('schema1');
        $this->adapter->createSchema('schema2');

        $refTable = new \Phinx\Db\Table('schema1.ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('schema2.table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'schema1.ref_table', ['id'])
            ->save();

        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropSchema('schema1');
        $this->adapter->dropSchema('schema2');
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

    public function testDropForeignKeyWithSchema()
    {
        $this->adapter->createSchema('schema1');
        $this->adapter->createSchema('schema2');

        $refTable = new \Phinx\Db\Table('schema1.ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('schema2.table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'schema1.ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));

        $this->adapter->dropSchema('schema1');
        $this->adapter->dropSchema('schema2');
    }

    public function testDropForeignKeyNotDroppingPrimaryKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [
            'id' => false,
            'primary_key' => ['ref_table_id'],
        ], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['id'])
            ->save();

        $table->dropForeignKey(['ref_table_id'])->save();
        $this->assertTrue($this->adapter->hasIndexByName('table', 'table_pkey'));
    }

    public function testHasDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('fake_database_name'));
        $this->assertTrue($this->adapter->hasDatabase(TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE));
    }

    public function testDropDatabase()
    {
        $this->assertFalse($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->createDatabase('phinx_temp_database');
        $this->assertTrue($this->adapter->hasDatabase('phinx_temp_database'));
        $this->adapter->dropDatabase('phinx_temp_database');
    }

    public function testCreateSchema()
    {
        $this->adapter->createSchema('foo');
        $this->assertTrue($this->adapter->hasSchema('foo'));
    }

    public function testDropSchema()
    {
        $this->adapter->createSchema('foo');
        $this->assertTrue($this->adapter->hasSchema('foo'));
        $this->adapter->dropSchema('foo');
        $this->assertFalse($this->adapter->hasSchema('foo'));
    }

    public function testDropAllSchemas()
    {
        $this->adapter->createSchema('foo');
        $this->adapter->createSchema('bar');

        $this->assertTrue($this->adapter->hasSchema('foo'));
        $this->assertTrue($this->adapter->hasSchema('bar'));
        $this->adapter->dropAllSchemas();
        $this->assertFalse($this->adapter->hasSchema('foo'));
        $this->assertFalse($this->adapter->hasSchema('bar'));
    }

    /**
     * @expectedException \Phinx\Db\Adapter\UnsupportedColumnTypeException
     * @expectedExceptionMessage Column type "idontexist" is not supported by Postgresql.
     */
    public function testInvalidSqlType()
    {
        $this->adapter->getSqlType('idontexist');
    }

    public function testGetPhinxType()
    {
        $this->assertEquals('integer', $this->adapter->getPhinxType('int'));
        $this->assertEquals('integer', $this->adapter->getPhinxType('int4'));
        $this->assertEquals('integer', $this->adapter->getPhinxType('integer'));

        $this->assertEquals('biginteger', $this->adapter->getPhinxType('bigint'));
        $this->assertEquals('biginteger', $this->adapter->getPhinxType('int8'));

        $this->assertEquals('decimal', $this->adapter->getPhinxType('decimal'));
        $this->assertEquals('decimal', $this->adapter->getPhinxType('numeric'));

        $this->assertEquals('float', $this->adapter->getPhinxType('real'));
        $this->assertEquals('float', $this->adapter->getPhinxType('float4'));

        $this->assertEquals('double', $this->adapter->getPhinxType('double precision'));

        $this->assertEquals('boolean', $this->adapter->getPhinxType('bool'));
        $this->assertEquals('boolean', $this->adapter->getPhinxType('boolean'));

        $this->assertEquals('string', $this->adapter->getPhinxType('character varying'));
        $this->assertEquals('string', $this->adapter->getPhinxType('varchar'));

        $this->assertEquals('text', $this->adapter->getPhinxType('text'));

        $this->assertEquals('time', $this->adapter->getPhinxType('time'));
        $this->assertEquals('time', $this->adapter->getPhinxType('timetz'));
        $this->assertEquals('time', $this->adapter->getPhinxType('time with time zone'));
        $this->assertEquals('time', $this->adapter->getPhinxType('time without time zone'));

        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamp'));
        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamptz'));
        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamp with time zone'));
        $this->assertEquals('datetime', $this->adapter->getPhinxType('timestamp without time zone'));

        $this->assertEquals('uuid', $this->adapter->getPhinxType('uuid'));

        $this->assertEquals('interval', $this->adapter->getPhinxType('interval'));
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

        $rows = $this->adapter->fetchAll(
            sprintf(
                "SELECT description FROM pg_description JOIN pg_class ON pg_description.objoid = " .
                "pg_class.oid WHERE relname = '%s'",
                'ntable'
            )
        );

        $this->assertEquals($tableComment, $rows[0]['description'], 'Dont set table comment correctly');
    }

    public function testCanAddColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn(
            'field1',
            'string',
            ['comment' => $comment = 'Comments from column "field1"']
        )->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\''
        );

        $this->assertEquals($comment, $row['column_comment'], 'Dont set column comment correctly');
    }

    public function testCanAddCommentForColumnWithReservedName()
    {
        $table = new \Phinx\Db\Table('user', [], $this->adapter);
        $table->addColumn('index', 'string', ['comment' => $comment = 'Comments from column "index"'])
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'user\'
            AND cols.column_name = \'index\''
        );

        $this->assertEquals(
            $comment,
            $row['column_comment'],
            'Dont set column comment correctly for tables or columns with reserved names'
        );
    }

    /**
     * @depends testCanAddColumnComment
     */
    public function testCanChangeColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
              ->save();

        $table->changeColumn(
            'field1',
            'string',
            ['comment' => $comment = 'New Comments from column "field1"']
        )->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\''
        );

        $this->assertEquals($comment, $row['column_comment'], 'Dont change column comment correctly');
    }

    /**
     * @depends testCanAddColumnComment
     */
    public function testCanRemoveColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
              ->save();

        $table->changeColumn('field1', 'string', ['comment' => 'null'])
              ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\''
        );

        $this->assertEmpty($row['column_comment'], 'Dont remove column comment correctly');
    }

    /**
     * @depends testCanAddColumnComment
     */
    public function testCanAddMultipleCommentsToOneTable()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('comment1', 'string', [
            'comment' => $comment1 = 'first comment'
            ])
            ->addColumn('comment2', 'string', [
            'comment' => $comment2 = 'second comment'
            ])
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'comment1\''
        );

        $this->assertEquals($comment1, $row['column_comment'], 'Could not create first column comment');

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'comment2\''
        );

        $this->assertEquals($comment2, $row['column_comment'], 'Could not create second column comment');
    }

    /**
     * @depends testCanAddColumnComment
     */
    public function testColumnsAreResetBetweenTables()
    {
        $table = new \Phinx\Db\Table('widgets', [], $this->adapter);
        $table->addColumn('transport', 'string', [
            'comment' => $comment = 'One of: car, boat, truck, plane, train'
            ])
            ->save();

        $table = new \Phinx\Db\Table('things', [], $this->adapter);
        $table->addColumn('speed', 'integer')
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\'' . TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE . '\'
            AND cols.table_name=\'widgets\'
            AND cols.column_name = \'transport\''
        );

        $this->assertEquals($comment, $row['column_comment'], 'Could not create column comment');
    }

    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testForeignKeysAreProperlyEscaped()
    {
        $userId = 'user';
        $sessionId = 'session';

        $local = new \Phinx\Db\Table('users', ['primary_key' => $userId, 'id' => $userId], $this->adapter);
        $local->create();

        $foreign = new \Phinx\Db\Table(
            'sessions',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('user', 'integer')
                ->addForeignKey('user', 'users', $userId)
                ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));
    }

    public function testForeignKeysAreProperlyEscapedWithSchema()
    {
        $this->adapter->createSchema('schema_users');

        $userId = 'user';
        $sessionId = 'session';

        $local = new \Phinx\Db\Table(
            'schema_users.users',
            ['primary_key' => $userId, 'id' => $userId],
            $this->adapter
        );
        $local->create();

        $foreign = new \Phinx\Db\Table(
            'schema_users.sessions',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('user', 'integer')
            ->addForeignKey('user', 'schema_users.users', $userId)
            ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));

        $this->adapter->dropSchema('schema_users');
    }

    public function testForeignKeysAreProperlyEscapedWithSchema2()
    {
        $this->adapter->createSchema('schema_users');
        $this->adapter->createSchema('schema_sessions');

        $userId = 'user';
        $sessionId = 'session';

        $local = new \Phinx\Db\Table(
            'schema_users.users',
            ['primary_key' => $userId, 'id' => $userId],
            $this->adapter
        );
        $local->create();

        $foreign = new \Phinx\Db\Table(
            'schema_sessions.sessions',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('user', 'integer')
            ->addForeignKey('user', 'schema_users.users', $userId)
            ->create();

        $this->assertTrue($foreign->hasForeignKey('user'));

        $this->adapter->dropSchema('schema_users');
        $this->adapter->dropSchema('schema_sessions');
    }

    public function testTimestampWithTimezone()
    {
        $table = new \Phinx\Db\Table('tztable', ['id' => false], $this->adapter);
        $table
            ->addColumn('timestamp_tz', 'timestamp', ['timezone' => true])
            ->addColumn('time_tz', 'time', ['timezone' => true])
            /* date columns cannot have timestamp */
            ->addColumn('date_notz', 'date', ['timezone' => true])
            /* default for timezone option is false */
            ->addColumn('time_notz', 'timestamp')
            ->save();

        $this->assertTrue($this->adapter->hasColumn('tztable', 'timestamp_tz'));
        $this->assertTrue($this->adapter->hasColumn('tztable', 'time_tz'));
        $this->assertTrue($this->adapter->hasColumn('tztable', 'date_notz'));
        $this->assertTrue($this->adapter->hasColumn('tztable', 'time_notz'));

        $columns = $this->adapter->getColumns('tztable');
        foreach ($columns as $column) {
            if (substr($column->getName(), -4) === 'notz') {
                $this->assertFalse($column->isTimezone(), 'column: ' . $column->getName());
            } else {
                $this->assertTrue($column->isTimezone(), 'column: ' . $column->getName());
            }
        }
    }

    public function testTimestampWithTimezoneWithSchema()
    {
        $this->adapter->createSchema('tzschema');

        $table = new \Phinx\Db\Table('tzschema.tztable', ['id' => false], $this->adapter);
        $table
            ->addColumn('timestamp_tz', 'timestamp', ['timezone' => true])
            ->addColumn('time_tz', 'time', ['timezone' => true])
            /* date columns cannot have timestamp */
            ->addColumn('date_notz', 'date', ['timezone' => true])
            /* default for timezone option is false */
            ->addColumn('time_notz', 'timestamp')
            ->save();

        $this->assertTrue($this->adapter->hasColumn('tzschema.tztable', 'timestamp_tz'));
        $this->assertTrue($this->adapter->hasColumn('tzschema.tztable', 'time_tz'));
        $this->assertTrue($this->adapter->hasColumn('tzschema.tztable', 'date_notz'));
        $this->assertTrue($this->adapter->hasColumn('tzschema.tztable', 'time_notz'));

        $columns = $this->adapter->getColumns('tzschema.tztable');
        foreach ($columns as $column) {
            if (substr($column->getName(), -4) === 'notz') {
                $this->assertFalse($column->isTimezone(), 'column: ' . $column->getName());
            } else {
                $this->assertTrue($column->isTimezone(), 'column: ' . $column->getName());
            }
        }

        $this->adapter->dropSchema('tzschema');
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

    public function testBulkInsertBoolean()
    {
        $data = [
            [
                'column1' => true,
            ],
            [
                'column1' => false,
            ],
            [
                'column1' => null,
            ],
        ];
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'boolean', ['null' => true])
            ->insert($data)
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertSame(true, $rows[0]['column1']);
        $this->assertSame(false, $rows[1]['column1']);
        $this->assertSame(null, $rows[2]['column1']);
    }

    public function testInsertData()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'integer')
              ->insert([
                  [
                      'column1' => 'value1',
                      'column2' => 1
                  ],
                  [
                      'column1' => 'value2',
                      'column2' => 2
                  ]
              ])
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
    }

    public function testInsertBoolean()
    {
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'boolean', ['null' => true])
            ->addColumn('column2', 'text', ['null' => true])
            ->insert([
                [
                    'column1' => true,
                    'column2' => 'value',
                ],
                [
                    'column1' => false,
                ],
                [
                    'column1' => null,
                ],
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertSame(true, $rows[0]['column1']);
        $this->assertSame(false, $rows[1]['column1']);
        $this->assertSame(null, $rows[2]['column1']);
    }

    public function testInsertDataWithSchema()
    {
        $this->adapter->createSchema('schema1');

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->insert([
                [
                    'column1' => 'value1',
                    'column2' => 1
                ],
                [
                    'column1' => 'value2',
                    'column2' => 2
                ]
            ])
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM "schema1"."table1"');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);

        $this->adapter->dropSchema('schema1');
    }

    public function testTruncateTable()
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
                  ]
              ])
              ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertCount(2, $rows);
        $table->truncate();
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertCount(0, $rows);
    }

    public function testTruncateTableWithSchema()
    {
        $this->adapter->createSchema('schema1');

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);
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

        $rows = $this->adapter->fetchAll('SELECT * FROM schema1.table1');
        $this->assertEquals(2, count($rows));
        $table->truncate();
        $rows = $this->adapter->fetchAll('SELECT * FROM schema1.table1');
        $this->assertEquals(0, count($rows));

        $this->adapter->dropSchema('schema1');
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

        $expectedOutput = 'CREATE TABLE "public"."table1" ("id" SERIAL NOT NULL, "column1" CHARACTER VARYING (255) ' .
        'NOT NULL, "column2" INTEGER NOT NULL, "column3" CHARACTER VARYING (255) NOT NULL DEFAULT \'test\', CONSTRAINT ' .
        '"table1_pkey" PRIMARY KEY ("id"));';
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains(
            $expectedOutput,
            $actualOutput,
            'Passing the --dry-run option does not dump create table query'
        );
    }

    public function testDumpCreateTableWithSchema()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();

        $expectedOutput = 'CREATE TABLE "schema1"."table1" ("id" SERIAL NOT NULL, "column1" CHARACTER VARYING (255) ' .
        'NOT NULL, "column2" INTEGER NOT NULL, "column3" CHARACTER VARYING (255) NOT NULL DEFAULT \'test\', CONSTRAINT ' .
        '"table1_pkey" PRIMARY KEY ("id"));';
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains(
            $expectedOutput,
            $actualOutput,
            'Passing the --dry-run option does not dump create table query'
        );
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
INSERT INTO "public"."table1" ("string_col") VALUES ('test data');
INSERT INTO "public"."table1" ("string_col") VALUES (null);
INSERT INTO "public"."table1" ("int_col") VALUES (23);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains(
            $expectedOutput,
            $actualOutput,
            'Passing the --dry-run option doesn\'t dump the insert to the output'
        );

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        self::assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0]['count']);
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
INSERT INTO "public"."table1" ("string_col", "int_col") VALUES ('test_data1', 23), (null, 42);
OUTPUT;
        $actualOutput = $consoleOutput->fetch();
        $this->assertContains(
            $expectedOutput,
            $actualOutput,
            'Passing the --dry-run option doesn\'t dump the bulkinsert to the output'
        );

        $countQuery = $this->adapter->query('SELECT COUNT(*) FROM table1');
        self::assertTrue($countQuery->execute());
        $res = $countQuery->fetchAll();
        $this->assertEquals(0, $res[0]['count']);
    }

    public function testDumpCreateTableAndThenInsert()
    {
        $inputDefinition = new InputDefinition([new InputOption('dry-run')]);
        $this->adapter->setInput(new ArrayInput(['--dry-run' => true], $inputDefinition));

        $consoleOutput = new BufferedOutput();
        $this->adapter->setOutput($consoleOutput);

        $table = new \Phinx\Db\Table('schema1.table1', ['id' => false, 'primary_key' => ['column1']], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->save();

        $expectedOutput = 'C';

        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);
        $table->insert([
            'column1' => 'id1',
            'column2' => 1
        ])->save();

        $expectedOutput = <<<'OUTPUT'
CREATE TABLE "schema1"."table1" ("column1" CHARACTER VARYING (255) NOT NULL, "column2" INTEGER NOT NULL, CONSTRAINT "table1_pkey" PRIMARY KEY ("column1"));
INSERT INTO "schema1"."table1" ("column1", "column2") VALUES ('id1', 1);
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
        $table = new \Phinx\Db\Table('schema1.table1', [], $this->adapter);

        $table->addColumn('column1', 'string')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'string', ['default' => 'test'])
            ->save();
        $this->adapter->commitTransaction();
        $this->adapter->rollbackTransaction();

        $actualOutput = $consoleOutput->fetch();
        $this->assertStringStartsWith("BEGIN;\n", $actualOutput, 'Passing the --dry-run doesn\'t dump the transaction to the output');
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
}
