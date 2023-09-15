<?php
declare(strict_types=1);

namespace Test\Phinx\Util;

use Phinx\Util\Expression;
use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    public function testToString()
    {
        $str = 'test1';
        $instance = new Expression($str);
        $this->assertEquals($str, (string)$instance);
    }

    public function testFrom()
    {
        $str = 'test1';
        $instance = Expression::from($str);
        $this->assertInstanceOf('\Phinx\Util\Expression', $instance);
        $this->assertEquals($str, (string)$instance);
    }
}
