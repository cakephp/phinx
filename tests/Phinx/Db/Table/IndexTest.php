<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Index;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "0" is not a valid index option.
     */
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $column = new Index();
        $column->setOptions(['type']);
    }
}
