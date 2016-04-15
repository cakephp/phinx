<?php

namespace Test\Phinx\Migration\Schema;

use Phinx\Db\Table;
use Phinx\Migration\Schema\Dumper;
use Phinx\Db\Table\Column;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Dumper
     */
    protected $schemaDumper;

    public function setUp()
    {
        $this->schemaDumper = new Dumper();
    }

    public function testDump()
    {
        $adapterStub = $this->getMock(
            '\Phinx\Db\Adapter\MysqlAdapter',
            array('getTables', 'getColumns', 'getForeignKeys'),
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
        $tableTwo = new Table('two', array('id'=>'two_id'), $adapterStub);
        $tableThree = new Table(
            'three',
            array('id'=>false, 'primary_key'=>array('dummy_int', 'dummy_int_null')),
            $adapterStub
        );

        $adapterStub->expects($this->once())
            ->method('getTables')
            ->will($this->returnValue(array($tableOne, $tableTwo, $tableThree)));

        $fk = new Table\ForeignKey();
        $fk->setColumns('dummy_int');
        $fk->setReferencedTable($tableTwo);
        $fk->setReferencedColumns(array('dummy_int_null'));

        $fkMap = array(
            array('three', array($fk))
        );
        $adapterStub->expects($this->any())
            ->method('getForeignKeys')
            ->will($this->returnValueMap($fkMap));

        $this->schemaDumper->setAdapter($adapterStub);
        $dump = $this->schemaDumper->dump();

        $this->assertStringEqualsFile(__DIR__ . '/../_files/schemadumper/schema.php', $dump);
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

        $columnPk = new Column();
        $columnPk->setName('two_id')
            ->setType('integer');

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

        return array($tableName, array($columnPk, $columnA, $columnB, $columnC));
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