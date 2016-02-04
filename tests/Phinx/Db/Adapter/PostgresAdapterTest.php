<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Output\NullOutput;
use Phinx\Db\Adapter\PostgresAdapter;

class PostgresAdapterTest extends PostgresAdapterTestCase
{
    /**
     * Check if Postgres is enabled in the current PHP
     *
     * @return boolean
     */
    private static function isPostgresAvailable()
    {
        static $available;

        if (is_null($available)) {
            $available = in_array('pgsql', \PDO::getAvailableDrivers());
        }

        return $available;
    }
    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_POSTGRES_ENABLED) {
            $this->markTestSkipped('Postgres tests disabled.  See TESTS_PHINX_DB_ADAPTER_POSTGRES_ENABLED constant.');
        }

        if (!self::isPostgresAvailable()) {
            $this->markTestSkipped('Postgres is not available.  Please install php-pdo-pgsql or equivalent package.');
        }

        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_POSTGRES_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_POSTGRES_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PORT,
            'schema' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE_SCHEMA
        );
        $this->adapter = new PostgresAdapter($options, new NullOutput());

        $this->adapter->dropAllSchemas();
        $this->adapter->createSchema($options['schema']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    public function testConnectionWithInvalidCredentials()
    {
        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_POSTGRES_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_POSTGRES_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        );

        try {
            $adapter = new PostgresAdapter($options, new NullOutput());
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

    public function providerArrayType()
    {
        return array(
            array('array_text', 'text[]'),
            array('array_char', 'char[]'),
            array('array_integer', 'integer[]'),
            array('array_float', 'float[]'),
            array('array_decimal', 'decimal[]'),
            array('array_timestamp', 'timestamp[]'),
            array('array_time', 'time[]'),
            array('array_date', 'date[]'),
            array('array_boolean', 'boolean[]'),
            array('array_json', 'json[]'),
            array('array_json2d', 'json[][]'),
            array('array_json3d', 'json[][][]'),
            array('array_uuid', 'uuid[]'),
        );
    }

    /**
     * @dataProvider providerArrayType
     */
    public function testAddColumnArrayType($column_name, $column_type)
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn($column_name));
        $table->addColumn($column_name, $column_type)
            ->save();
        $this->assertTrue($table->hasColumn($column_name));
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

    public function testChangeColumnWithDefault() {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setName('column1')
                   ->setType('string')
                   ->setNull(true);

        $newColumn1->setDefault('Test');
        $table->changeColumn('column1', $newColumn1);

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertTrue($column->isNull());
                $this->assertRegExp('/Test/', $column->getDefault());
            }
        }
    }

    public function testChangeColumnWithDropDefault() {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string', array('default' => 'Test'))
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

        $table->changeColumn('column1', $newColumn1);

        $columns = $this->adapter->getColumns('t');
        foreach ($columns as $column) {
            if ($column->getName() === 'column1') {
                $this->assertNull($column->getDefault());
            }
        }
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

    public function testTimestampWithTimezone()
    {
        $table = new \Phinx\Db\Table('tztable', array('id' => false), $this->adapter);
        $table
            ->addColumn('timestamp_tz', 'timestamp', array('timezone' => true))
            ->addColumn('time_tz', 'time', array('timezone' => true))
            ->addColumn('date_notz', 'date', array('timezone' => true)) /* date columns cannot have timestamp */
            ->addColumn('time_notz', 'timestamp') /* default for timezone option is false */
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

    /**
     * @depends testCanAddColumnComment
     */
    public function testCanChangeColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('field1', 'string', array('comment' => 'Comments from column "field1"'))
              ->save();

        $table->changeColumn('field1', 'string', array('comment' => $comment = 'New Comments from column "field1"'))
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

        $this->assertEquals($comment, $row['column_comment'], 'Dont change column comment correctly');
    }

    /**
     * @depends testCanAddColumnComment
     */
    public function testCanRemoveColumnComment()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('field1', 'string', array('comment' => 'Comments from column "field1"'))
              ->save();

        $table->changeColumn('field1', 'string', array('comment' => 'null'))
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

        $this->assertEmpty($row['column_comment'], 'Dont remove column comment correctly');
    }

    /**
     * {@inheritdoc}
     */
    protected function getDatabase()
    {
        return TESTS_PHINX_DB_ADAPTER_POSTGRES_DATABASE;
    }
}
