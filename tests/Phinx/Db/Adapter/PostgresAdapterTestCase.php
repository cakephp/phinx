<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Output\NullOutput;
use Phinx\Db\Adapter\PostgresAdapter;

abstract class PostgresAdapterTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phinx\Db\Adapter\PostgresqlAdapter
     */
    protected $adapter;

    public function tearDown()
    {
        if ($this->adapter) {
            $this->adapter->dropAllSchemas();
            unset($this->adapter);
        }
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

    public function testQuoteSchemaName()
    {
        $this->assertEquals('"schema"', $this->adapter->quoteSchemaName('schema'));
        $this->assertEquals('"schema.schema"', $this->adapter->quoteSchemaName('schema.schema'));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('"public"."table"', $this->adapter->quoteTableName('table'));
        $this->assertEquals('"public"."table.table"', $this->adapter->quoteTableName('table.table'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('"string"', $this->adapter->quoteColumnName('string'));
        $this->assertEquals('"string.string"', $this->adapter->quoteColumnName('string.string'));
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
            'id'            => false,
            'primary_key'   => array('user_id', 'tag_id')
        );
        $table = new \Phinx\Db\Table('table1', $options, $this->adapter);
        $table->addColumn('user_id', 'integer')
              ->addColumn('tag_id', 'integer')
              ->save();
        $this->assertTrue($this->adapter->hasIndex('table1', array('user_id', 'tag_id')));
        $this->assertTrue($this->adapter->hasIndex('table1', array('tag_id', 'USER_ID')));
        $this->assertFalse($this->adapter->hasIndex('table1', array('tag_id', 'user_email')));
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
        $table->addColumn('email', 'string', array('null' => true))
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
                $this->assertEquals("'test'::character varying", $column->getDefault());
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

    public function testAddColumnWithDefaultBoolean()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('default_true', 'boolean', array('default' => true))
              ->addColumn('default_false', 'boolean', array('default' => false))
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
        }
    }

    public function testAddDecimalWithPrecisionAndScale()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $table->addColumn('number', 'decimal', array('precision' => 10, 'scale' => 2, 'default' => 0))
            ->addColumn('number2', 'decimal', array('limit' => 12, 'default' => 0))
            ->addColumn('number3', 'decimal', array('default' => 0))
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
              ->addColumn('column2', 'integer', array('limit' => PostgresAdapter::INT_SMALL))
              ->addColumn('column3', 'integer')
              ->addColumn('column4', 'biginteger')
              ->addColumn('column5', 'text')
              ->addColumn('column6', 'float')
              ->addColumn('column7', 'decimal')
              ->addColumn('column8', 'timestamp')
              ->addColumn('column9', 'date')
              ->addColumn('column10', 'boolean')
              ->addColumn('column11', 'datetime')
              ->addColumn('column12', 'string', array('limit' => 10));
        $pendingColumns = $table->getPendingColumns();
        $table->save();
        $columns = $this->adapter->getColumns('t');
        $this->assertCount(count($pendingColumns) + 1, $columns);
        for ($i = 0; $i++; $i < count($pendingColumns)) {
            $this->assertEquals($pendingColumns[$i], $columns[$i+1]);
        }
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

    public function testDropForeignKey()
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
        $this->assertTrue($this->adapter->hasDatabase($this->getDatabase()));
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
        $this->assertEquals('integer', $this->adapter->getPhinxType('int4'));
        $this->assertEquals('integer', $this->adapter->getPhinxType('integer'));

        $this->assertEquals('biginteger', $this->adapter->getPhinxType('bigint'));
        $this->assertEquals('biginteger', $this->adapter->getPhinxType('int8'));

        $this->assertEquals('decimal', $this->adapter->getPhinxType('decimal'));
        $this->assertEquals('decimal', $this->adapter->getPhinxType('numeric'));

        $this->assertEquals('float', $this->adapter->getPhinxType('real'));
        $this->assertEquals('float', $this->adapter->getPhinxType('float4'));

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

    }

    public function testCanAddColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('field1', 'string', array('comment' => $comment = 'Comments from column "field1"'))
              ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\''. $this->getDatabase() .'\'
            AND cols.table_name=\'table1\'
            AND cols.column_name = \'field1\''
        );

        $this->assertEquals($comment, $row['column_comment'], 'Dont set column comment correctly');
    }

    /**
     * @depends testCanAddColumnComment
     */
    public function testCanAddMultipleCommentsToOneTable()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('comment1', 'string', array(
            'comment' => $comment1 = 'first comment'
            ))
            ->addColumn('comment2', 'string', array(
            'comment' => $comment2 = 'second comment'
            ))
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\''. $this->getDatabase() .'\'
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
            WHERE cols.table_catalog=\''. $this->getDatabase() .'\'
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
        $table = new \Phinx\Db\Table('widgets', array(), $this->adapter);
        $table->addColumn('transport', 'string', array(
            'comment' => $comment = 'One of: car, boat, truck, plane, train'
            ))
            ->save();

        $table = new \Phinx\Db\Table('things', array(), $this->adapter);
        $table->addColumn('speed', 'integer')
            ->save();

        $row = $this->adapter->fetchRow(
            'SELECT
                (select pg_catalog.col_description(oid,cols.ordinal_position::int)
            from pg_catalog.pg_class c
            where c.relname=cols.table_name ) as column_comment
            FROM information_schema.columns cols
            WHERE cols.table_catalog=\''. $this->getDatabase() .'\'
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
            ->insert(
                array("column1", "column2"),
                array(
                    array('value1', 1),
                    array('value2', 2)
                )
            )
            ->save();

        $rows = $this->adapter->fetchAll('SELECT * FROM table1 ORDER BY column1');

        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
    }

    abstract protected function getDatabase();
}
