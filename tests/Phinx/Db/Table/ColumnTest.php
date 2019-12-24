<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ColumnTest extends TestCase
{
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid column option.');
        $column = new Column();
        $column->setOptions(['identity']);
    }
}
