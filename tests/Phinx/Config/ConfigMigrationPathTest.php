<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigMigrationPathTest
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getMigrationPath
 */
class ConfigMigrationPathTest extends AbstractConfigTest
{
    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGetMigrationPathThrowsExceptionForNoPath()
    {
        $config = new Config(array());
        $config->getMigrationPath();
    }

    /**
     * Normal behavior
     */
    public function testGetMigrationPath()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getMigrationPath(), $config->getMigrationPath());
    }
}