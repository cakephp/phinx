<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "0" is not a valid column option.
     */
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $column = new Column();
        $column->setOptions(['identity']);
    }
}
