<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Index;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IndexTest extends TestCase
{
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $column = new Index();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid index option.');

        $column->setOptions(['type']);
    }
}
