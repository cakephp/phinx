<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Column;

class ColumnTest extends \PHPUnit_Framework_TestCase
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
