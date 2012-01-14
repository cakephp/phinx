<?php

namespace Test\Phinx\Migration;

use Symfony\Component\Console\Output\StreamOutput,
    Phinx\Config\Config,
    Phinx\Migration\Manager;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testInstantiation()
    {
        $config = new Config(array('foo' => 'bar'));
        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $manager = new Manager($config, $output);
        $this->assertTrue($manager->getOutput() instanceof StreamOutput);
    }
}