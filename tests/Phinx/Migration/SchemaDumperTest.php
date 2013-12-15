<?php

namespace Test\Phinx\Migration;

use Phinx\Db\Table;
use Phinx\Migration\SchemaDumper;
use Phinx\Db\Table\Column;

class SchemaDumperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SchemaDumper
     */
    protected $schemaDumper;

    public function setUp()
    {
        $this->schemaDumper = new SchemaDumper();
    }

    public function testDump()
    {
        $adapterStub = $this->getMock(
            '\Phinx\Db\Adapter\MysqlAdapter',
            array('getTables', 'getColumns'),
            array(array())
        );

        $map = array(
            $this->setupTableOneStub(),
            $this->setupTableTwoStub(),
            $this->setupTableThreeStub(),
        );

        $adapterStub->expects($this->any())
            ->method('getColumns')
            ->will($this->returnValueMap($map));

        $tableOne = new Table('one', array(), $adapterStub);
        $tableTwo = new Table('two', array(), $adapterStub);
        $tableThree = new Table('three', array(), $adapterStub);

        $adapterStub->expects($this->once())
            ->method('getTables')
            ->will($this->returnValue(array($tableOne, $tableTwo, $tableThree)));

        $this->schemaDumper->setAdapter($adapterStub);
        $dump = $this->schemaDumper->dump();

        $this->assertStringEqualsFile(__DIR__ . '/_files/schemadumper/schema.php', $dump);
    }

    protected function setupTableOneStub()
    {
        $tableName = 'one';

        $columnA = new Column();
        $columnA->setName('name')
            ->setType('string')
            ->setLimit(50);
        $columnB = new Column();
        $columnB->setName('dummy_int')
            ->setType('integer');
        $columnC = new Column();
        $columnC->setName('dummy_int_null')
            ->setType('integer')
            ->setNull(true);

        return array($tableName, array($columnA, $columnB, $columnC));
    }

    protected function setupTableTwoStub()
    {
        $tableName = 'two';

        $columnA = new Column();
        $columnA->setName('name')
            ->setType('string')
            ->setLimit(50);
        $columnB = new Column();
        $columnB->setName('dummy_int')
            ->setType('integer');
        $columnC = new Column();
        $columnC->setName('dummy_int_null')
            ->setType('integer')
            ->setNull(true);

        return array($tableName, array($columnA, $columnB, $columnC));
    }

    protected function setupTableThreeStub()
    {
        $tableName = 'three';

        $columnA = new Column();
        $columnA->setName('name')
            ->setType('string')
            ->setLimit(50);
        $columnB = new Column();
        $columnB->setName('dummy_int')
            ->setType('integer');
        $columnC = new Column();
        $columnC->setName('dummy_int_null')
            ->setType('integer')
            ->setNull(true);

        return array($tableName, array($columnA, $columnB, $columnC));
    }
} 