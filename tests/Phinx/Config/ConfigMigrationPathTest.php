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
        $config->getMigrationPaths();
    }

    /**
     * Normal behavior
     */
    public function testGetMigrationPath()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getMigrationPath(), $config->getMigrationPaths());
    }

    public function testGetMigrationPathConvertsStringToArray()
    {
        $values = array(
            'paths' => array(
                'migrations' => '/test'
            )
        );
        $config = new Config($values);
        $paths = $config->getMigrationPaths();
        $this->assertTrue(is_array($paths));
        $this->assertTrue(count($paths) == 1);
    }
}