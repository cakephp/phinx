<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigMigrationPathsTest
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getMigrationPaths
 */
class ConfigMigrationPathsTest extends AbstractConfigTest
{
    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGetMigrationPathsThrowsExceptionForNoPath()
    {
        $config = new Config(array());
        $config->getMigrationPaths();
    }

    /**
     * Normal behavior
     */
    public function testGetMigrationPaths()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getMigrationPaths(), $config->getMigrationPaths());
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
        $this->assertTrue(count($paths) === 1);
    }
}