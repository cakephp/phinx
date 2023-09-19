<?php
declare(strict_types=1);

namespace Test\Phinx\Util;

use Phinx\Util\Literal;
use PHPUnit\Framework\TestCase;

class LiteralTest extends TestCase
{
    public function testToString()
    {
        $str = 'test1';
        $instance = new Literal($str);
        $this->assertEquals($str, (string)$instance);
    }

    public function testFrom()
    {
        $str = 'test1';
        $instance = Literal::from($str);
        $this->assertInstanceOf('\Phinx\Util\Literal', $instance);
        $this->assertEquals($str, (string)$instance);
    }
}
