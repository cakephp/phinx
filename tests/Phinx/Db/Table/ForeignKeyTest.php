<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Table;

use InvalidArgumentException;
use Phinx\Db\Table\ForeignKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ForeignKeyTest extends TestCase
{
    /**
     * @var ForeignKey
     */
    private $fk;

    protected function setUp(): void
    {
        $this->fk = new ForeignKey();
    }

    public function testOnDeleteSetNullCanBeSetThroughOptions()
    {
        $this->assertEquals(
            ForeignKey::SET_NULL,
            $this->fk->setOptions(['delete' => ForeignKey::SET_NULL])->getOnDelete(),
        );
    }

    public function testInitiallyActionsEmpty()
    {
        $this->assertNull($this->fk->getOnDelete());
        $this->assertNull($this->fk->getOnUpdate());
    }

    public function testRefrencedTableUndefined()
    {
        $this->expectException(RuntimeException::class);
        $this->fk->getReferencedTable();
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
     * @param string $dirtyValue
     * @param string $valueOfConstant
     * @dataProvider deferrableProvider
     */
    public function testDeferrableCanBeSetThroughSetters(string $dirtyValue, string $valueOfConstant): void
    {
        $this->fk->setDeferrableMode($dirtyValue);
        $this->assertEquals($valueOfConstant, $this->fk->getDeferrableMode());
    }

    /**
     * @param string $dirtyValue
     * @param string $valueOfConstant
     * @dataProvider deferrableProvider
     */
    public function testDeferrableCanBeSetThroughOptions(string $dirtyValue, string $valueOfConstant): void
    {
        $this->fk->setOptions([
            'deferrable' => $dirtyValue,
        ]);
        $this->assertEquals($valueOfConstant, $this->fk->getDeferrableMode());
    }

    public function testThrowsErrorForInvalidDeferrableValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fk->setDeferrableMode('invalid_value');
    }

    public function testUnknownActionsNotAllowedThroughSetter()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->fk->setOnDelete('i m dump');
    }

    public function testUnknownActionsNotAllowedThroughOptions()
    {
        $this->expectException(InvalidArgumentException::class);

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

    public function deferrableProvider(): array
    {
        return [
            ['DEFERRED', ForeignKey::DEFERRED],
            ['IMMEDIATE', ForeignKey::IMMEDIATE],
            ['NOT_DEFERRED', ForeignKey::NOT_DEFERRED],
            ['Deferred', ForeignKey::DEFERRED],
            ['Immediate', ForeignKey::IMMEDIATE],
            ['Not_deferred', ForeignKey::NOT_DEFERRED],
            [ForeignKey::DEFERRED, ForeignKey::DEFERRED],
            [ForeignKey::IMMEDIATE, ForeignKey::IMMEDIATE],
            [ForeignKey::NOT_DEFERRED, ForeignKey::NOT_DEFERRED],
        ];
    }

    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid foreign key option');

        $this->fk->setOptions(['update']);
    }
}
