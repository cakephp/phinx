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

    /**
     * {@inheritdoc}
     */
    protected function getDatabase()
    {
        return TESTS_PHINX_DB_ADAPTER_REDSHIFT_DATABASE;
    }
}
