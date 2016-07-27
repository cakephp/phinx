<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Index;

class IndexTest extends \PHPUnit_Framework_TestCase
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
