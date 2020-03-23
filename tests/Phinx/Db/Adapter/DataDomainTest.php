<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\AbstractAdapter;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\PostgresAdapter;
use PHPUnit\Framework\TestCase;

class DataDomainTest extends TestCase
{

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage You must specify a type for data domain type "phone_number".
     */
    public function testThrowsIfNoTypeSpecified()
    {
        $data_domain = [
            "phone_number" => [
                "length" => 19,
            ],
        ];

        $adapter = new MysqlAdapter(['data_domain' => $data_domain]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage An invalid column type "str" was specified for data domain type "phone_number".
     */
    public function testThrowsIfInvalidBaseType()
    {
        $data_domain = [
            'phone_number' => [
                'type' => 'str', // _Must be_ an invalid Phinx type
                'length' => 19,
            ],
        ];

        $adapter = new MysqlAdapter(['data_domain' => $data_domain]);
    }

    /**
     *
     */
    public function testConvertsToInternalType()
    {
        $data_domain = [
            'phone_number' => [
                'type' => 'string',
                'length' => 19,
            ],
        ];

        $mysql_adapter = new MysqlAdapter(['data_domain' => $data_domain]);
        $dd = $mysql_adapter->getDataDomain();

        $this->assertEquals($data_domain['phone_number']['type'], $dd['phone_number']['type']);
    }

    /**
     *
     */
    public function testReplacesLengthForLimit()
    {
        $data_domain = [
            'phone_number' => [
                'type' => 'string',
                'length' => 19,
            ],
        ];

        $mysql_adapter = new MysqlAdapter(['data_domain' => $data_domain]);
        $dd = $mysql_adapter->getDataDomain();

        $this->assertInternalType('array', $dd['phone_number']['options']);
        $this->assertEquals(19, $dd['phone_number']['options']['limit']);
    }

    /**
     *
     */
    public function testConvertsToMysqlLimit()
    {
        $data_domain = [
            'prime' => [
                'type' => 'integer',
                'limit' => 'INT_BIG',
            ],
        ];

        $mysql_adapter = new MysqlAdapter(['data_domain' => $data_domain]);
        $dd = $mysql_adapter->getDataDomain();

        $this->assertEquals(MysqlAdapter::INT_BIG, $dd['prime']['options']['limit']);
    }

    /**
     *
     */
    public function testCreatesTypeFromPhinxConstant()
    {
        $data_domain = [
            'prime' => [
                'type' => 'PHINX_TYPE_INTEGER',
                'limit' => 'INT_BIG',
            ],
        ];

        $mysql_adapter = new MysqlAdapter(['data_domain' => $data_domain]);
        $dd = $mysql_adapter->getDataDomain();

        $this->assertEquals(MysqlAdapter::PHINX_TYPE_INTEGER, $dd['prime']['type']);
        $this->assertEquals(MysqlAdapter::INT_BIG, $dd['prime']['options']['limit']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage An invalid limit value "BIG_SUR" was specified for data domain type "prime".
     */
    public function testThrowsErrorForInvalidMysqlLimit()
    {
        $data_domain = [
            'prime' => [
                'type' => 'integer',
                'limit' => 'BIG_SUR',
            ],
        ];

        $mysql_adapter = new MysqlAdapter(['data_domain' => $data_domain]);
    }

    /**
     *
     */
    public function testCreatesColumnWithDataDomain()
    {
        $data_domain = [
            'phone_number' => [
                'type' => 'string',
                'length' => 19,
            ],
        ];

        $adapter = new MysqlAdapter(['data_domain' => $data_domain]);
        $column = $adapter->getColumnForType('phone', 'phone_number', []);

        $this->assertEquals('string', $column->getType());
        $this->assertEquals(19, $column->getLimit());
    }

    /**
     *
     */
    public function testLocalOptionsOverridesDataDomainOptions()
    {
        $data_domain = [
            'phone_number' => [
                'type' => 'string',
                'length' => 19,
            ],
        ];

        $adapter = new MysqlAdapter(['data_domain' => $data_domain]);
        $column = $adapter->getColumnForType('phone', 'phone_number', ['length' => 30]);

        $this->assertEquals('string', $column->getType());
        $this->assertEquals(30, $column->getLimit());
    }
}
