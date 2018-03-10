<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\ForeignKey;
use PHPUnit\Framework\TestCase;

class ForeignKeyTest extends TestCase
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
            $this->fk->setOptions(['delete' => ForeignKey::SET_NULL])->getOnDelete()
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
        $this->fk->setOptions([
            'delete' => $dirtyValue,
            'update' => $dirtyValue,
        ]);
        $this->assertEquals($valueOfConstant, $this->fk->getOnDelete());
        $this->assertEquals($valueOfConstant, $this->fk->getOnUpdate());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownActionsNotAllowedThroughSetter()
    {
        $this->fk->setOnDelete('i m dump');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownActionsNotAllowedThroughOptions()
    {
        $this->fk->setOptions(['update' => 'no yu a dumb']);
    }

    public function actionsProvider()
    {
        return [
            [ForeignKey::CASCADE, ForeignKey::CASCADE],
            [ForeignKey::RESTRICT, ForeignKey::RESTRICT],
            [ForeignKey::NO_ACTION, ForeignKey::NO_ACTION],
            [ForeignKey::SET_NULL, ForeignKey::SET_NULL],
            ['no Action ', ForeignKey::NO_ACTION],
            ['Set nuLL', ForeignKey::SET_NULL],
            ['no_Action', ForeignKey::NO_ACTION],
            ['Set_nuLL', ForeignKey::SET_NULL],
        ];
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "0" is not a valid foreign key option.
     */
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $this->fk->setOptions(['update']);
    }
}
