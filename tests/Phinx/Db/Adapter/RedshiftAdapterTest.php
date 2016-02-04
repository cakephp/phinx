<?php

namespace Test\Phinx\Db\Adapter;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Phinx\Db\Adapter\RedshiftAdapter;

class RedshiftAdapterTest extends PostgresAdapterTestCase
{
    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_REDSHIFT_ENABLED) {
            $this->markTestSkipped('Redshift tests disabled.  See TESTS_PHINX_DB_ADAPTER_REDSHIFT_ENABLED constant.');
        }

        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_DATABASE,
            'user' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_PORT,
            'schema' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_DATABASE_SCHEMA
        );
        $this->adapter = new RedshiftAdapter($options, new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG));

        $this->adapter->dropAllSchemas();
        $this->adapter->createSchema($options['schema']);

        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    public function testConnectionWithInvalidCredentials()
    {
        $options = array(
            'host' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_HOST,
            'name' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_DATABASE,
            'port' => TESTS_PHINX_DB_ADAPTER_REDSHIFT_PORT,
            'user' => 'invaliduser',
            'pass' => 'invalidpass'
        );

        try {
            $adapter = new RedshiftAdapter($options, new NullOutput());
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

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage CREATE [UNIQUE] INDEX is not supported on Redshift.
     */
    public function testCreateTableWithIndexesThrowsException()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->addColumn('name', 'string')
              ->addIndex('email')
              ->addIndex('name')
              ->save();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Redshift does not support 'CHANGE COLUMN'. Please DROP and recreate the column.
     */
    public function testChangeColumnThrowsException()
    {
        $table = new \Phinx\Db\Table('t', array(), $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();
        $this->assertTrue($this->adapter->hasColumn('t', 'column1'));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1->setType('string');
        $table->changeColumn('column1', $newColumn1);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage CREATE [UNIQUE] INDEX is not supported on Redshift.
     */
    public function testAddIndexThrowsException()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->assertFalse($table->hasIndex('email'));
        $table->addIndex('email')
              ->save();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage DROP INDEX is not supported on Redshift.
     */
    public function testDropIndexThrowsException()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->adapter->dropIndex($table->getName(), 'email');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage DROP INDEX is not supported on Redshift.
     */
    public function testDropIndexByNameThrowsException()
    {
        $table = new \Phinx\Db\Table('table1', array(), $this->adapter);
        $table->addColumn('email', 'string')
              ->save();
        $this->adapter->dropIndexByName($table->getName(), 'myemailindex');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Redshift does not support 'TIMESTAMP WITH TIME ZONE'
     */
    public function testTimestampWithTimezone()
    {
        $table = new \Phinx\Db\Table('tztable', array('id' => false), $this->adapter);
        $table
            ->addColumn('timestamp_tz', 'timestamp', array('timezone' => true))
            ->save();
    }

    public function testCompoundSortKey()
    {
        $table = new \Phinx\Db\Table('sort_table', array('sortkey' => array('sort_column')), $this->adapter);
        $table
            ->addColumn('sort_column', 'integer')
            ->addColumn('nonsort_column', 'integer')
            ->save();

        $key = $this->adapter->getSortKey('sort_table');

        $this->assertTrue($this->adapter->hasSortKey('sort_table', 'sort_column'), '`sort_column` should be a sortkey column');
        $this->assertArrayHasKey('type', $key);
        $this->assertArrayHasKey('columns', $key);
        $this->assertCount(1, $key['columns']);
        $this->assertEquals(RedshiftAdapter::SORTKEY_COMPOUND, $key['type'], '`sortkey` should be compound');

    }

    public function testInterleavedSortKey()
    {
        $table = new \Phinx\Db\Table('sort_table', array('sortkey' => array(
            'type' => 'interleaved',
            'columns' => array('column1', 'column2', 'column3'),
        )), $this->adapter);
        $table
            ->addColumn('column1', 'integer')
            ->addColumn('column2', 'integer')
            ->addColumn('column3', 'integer')
            ->addColumn('nonsort_column', 'integer')
            ->save();

        $key = $this->adapter->getSortKey('sort_table');

        $this->assertArrayHasKey('type', $key);
        $this->assertArrayHasKey('columns', $key);
        $this->assertCount(3, $key['columns']);
        $this->assertEquals(RedshiftAdapter::SORTKEY_INTERLEAVED, $key['type'], '`sortkey` should be interleaved');
        $this->assertEquals('column1', $key['columns'][0], '`column1` should be position 1');
        $this->assertEquals('column2', $key['columns'][1], '`column2` should be position 2');
        $this->assertEquals('column3', $key['columns'][2], '`column3` should be position 3');
    }

    public function testDistKey()
    {
        $table = new \Phinx\Db\Table('dist_table', array('distkey' => 'dist_column'), $this->adapter);
        $table
            ->addColumn('dist_column', 'integer')
            ->addColumn('nondist_column', 'integer')
            ->save();

        $this->assertEquals('dist_column', $this->adapter->getDistKey('dist_table'), '`dist_column` should be the distkey column');
    }

    public function testDistStyle()
    {
        $table = new \Phinx\Db\Table('dist_key_table', array(
            'diststyle' => RedshiftAdapter::DISTSTYLE_KEY,
            'distkey' => 'dist_column'
        ), $this->adapter);
        $table
            ->addColumn('dist_column', 'integer')
            ->save();

        $table = new \Phinx\Db\Table('dist_even_table', array(
            'diststyle' => RedshiftAdapter::DISTSTYLE_EVEN,
        ), $this->adapter);
        $table
            ->addColumn('some_column', 'integer')
            ->save();

        $table = new \Phinx\Db\Table('dist_all_table', array(
            'diststyle' => RedshiftAdapter::DISTSTYLE_ALL,
        ), $this->adapter);
        $table
            ->addColumn('some_column', 'integer')
            ->save();

        $this->assertEquals(RedshiftAdapter::DISTSTYLE_KEY, $this->adapter->getDistStyle('dist_key_table'), '`diststyle` should be the key distribution');
        $this->assertEquals(RedshiftAdapter::DISTSTYLE_EVEN, $this->adapter->getDistStyle('dist_even_table'), '`diststyle` should be the even distribution');
        $this->assertEquals(RedshiftAdapter::DISTSTYLE_ALL, $this->adapter->getDistStyle('dist_all_table'), '`diststyle` should be the all distribution');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Must set a DISTKEY when using DISTSTYLE KEY
     */
    public function testDistStyleKeyWithoutDistKeyThrowException()
    {
        $table = new \Phinx\Db\Table('dist_key_table', array(
            'diststyle' => RedshiftAdapter::DISTSTYLE_KEY,
        ), $this->adapter);
        $table
            ->addColumn('nondist_column', 'integer')
            ->save();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDatabase()
    {
        return TESTS_PHINX_DB_ADAPTER_REDSHIFT_DATABASE;
    }
}
