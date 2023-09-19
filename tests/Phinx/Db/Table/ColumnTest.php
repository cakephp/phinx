<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Table;

use Phinx\Config\FeatureFlags;
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
}
