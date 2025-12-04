<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Table;

use Phinx\Config\FeatureFlags;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ColumnTest extends TestCase
{
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $column = new Column();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid column option.');

        $column->setOptions(['identity']);
    }

    public function testGetType()
    {
        $column = new Column();
        $this->expectException(RuntimeException::class);
        $column->getType();
    }

    public function testSetOptionsIdentity()
    {
        $column = new Column();
        $this->assertTrue($column->isNull());
        $this->assertFalse($column->isIdentity());

        $column->setOptions(['identity' => true]);
        $this->assertFalse($column->isNull());
        $this->assertTrue($column->isIdentity());
    }

    /**
     * @runInSeparateProcess
     */
    public function testColumnNullFeatureFlag()
    {
        $column = new Column();
        $this->assertTrue($column->isNull());

        FeatureFlags::$columnNullDefault = false;
        $column = new Column();
        $this->assertFalse($column->isNull());
    }

    public function testSetAlgorithm(): void
    {
        $column = new Column();
        $this->assertNull($column->getAlgorithm());

        $column->setOptions(['algorithm' => MysqlAdapter::ALGORITHM_INPLACE]);
        $this->assertSame(MysqlAdapter::ALGORITHM_INPLACE, $column->getAlgorithm());
    }

    public function testSetLock(): void
    {
        $column = new Column();
        $this->assertNull($column->getLock());

        $column->setOptions(['lock' => MysqlAdapter::LOCK_NONE]);
        $this->assertSame(MysqlAdapter::LOCK_NONE, $column->getLock());
    }
}
