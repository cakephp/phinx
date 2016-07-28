<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigSeedPathsTest
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getSeedPaths
 */
class ConfigSeedPathsTest extends AbstractConfigTest
{
    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGetSeedPathsThrowsExceptionForNoPath()
    {
        $config = new Config(array());
        $config->getSeedPaths();
    }

    /**
     * Normal behavior
     */
    public function testGetSeedPaths()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getSeedPaths(), $config->getSeedPaths());
    }

    public function testGetSeedPathConvertsStringToArray()
    {
        $values = array(
            'paths' => array(
                'seeds' => '/test'
            )
        );

        $config = new Config($values);
        $paths = $config->getSeedPaths();

        $this->assertTrue(is_array($paths));
        $this->assertTrue(count($paths) === 1);
    }
}