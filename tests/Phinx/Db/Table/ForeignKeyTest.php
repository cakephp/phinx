<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\ForeignKey;

class ForeignKeyTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ForeignKey
     */
    private $fk = null;

    protected function setUp()
    {
        $this->fk = new ForeignKey();
    }

    public function testOnDeleteSetNullCanBeSetThroughOptions()
    {
        $this->assertEquals(
            ForeignKey::SET_NULL,
            $this->fk->setOptions(array('delete' => ForeignKey::SET_NULL))->getOnDelete()
        );
    }

    public function testInitiallyActionsEmpty()
    {
        $this->assertNull($this->fk->getOnDelete());
        $this->assertNull($this->fk->getOnUpdate());
    }

    /**
     * @param string $dirtyValue
     * @param string $valueOfConstant
     * @dataProvider actionsProvider
     */
    public function testBothActionsCanBeSetThroughSetters($dirtyValue, $valueOfConstant)
    {
        $this->fk->setOnDelete($dirtyValue)->setOnUpdate($dirtyValue);
        $this->assertEquals($valueOfConstant, $this->fk->getOnDelete());
        $this->assertEquals($valueOfConstant, $this->fk->getOnUpdate());
    }

    /**
     * @param string $dirtyValue
     * @param string $valueOfConstant
     * @dataProvider actionsProvider
     */
    public function testBothActionsCanBeSetThroughOptions($dirtyValue, $valueOfConstant)
    {
        $this->fk->setOptions(array(
            'delete' => $dirtyValue,
            'update' => $dirtyValue,
        ));
        $this->assertEquals($valueOfConstant, $this->fk->getOnDelete());
        $this->assertEquals($valueOfConstant, $this->fk->getOnUpdate());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownActionsNotAlowedThroughSetter()
    {
        $this->fk->setOnDelete('i m dump');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownActionsNotAlowedThroughOptions()
    {
        $this->fk->setOptions(array('update' => 'no yu a dumb'));
    }

    public function actionsProvider()
    {
        return array(
            array(ForeignKey::CASCADE,   ForeignKey::CASCADE),
            array(ForeignKey::RESTRICT,  ForeignKey::RESTRICT),
            array(ForeignKey::NO_ACTION, ForeignKey::NO_ACTION),
            array(ForeignKey::SET_NULL,  ForeignKey::SET_NULL),
            array('no Action ',          ForeignKey::NO_ACTION),
            array('Set nuLL',            ForeignKey::SET_NULL),
            array('no_Action',           ForeignKey::NO_ACTION),
            array('Set_nuLL',            ForeignKey::SET_NULL),
        );
    }
}
