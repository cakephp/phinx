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
        $config = new Config([]);
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

    /**
     * Normal behavior
     */
    public function testGetSeedPathsWithMultiDb()
    {
        $config = new Config($this->getConfigArrayWithMultiDb());
        $this->assertEquals($this->getSeedPathsWithMultiDb(), $config->getSeedPaths('testing', 'db1'));
        $this->assertEquals([$this->getSeedPathsWithMultiDbAsString()], $config->getSeedPaths('production', 'db1'));
    }

    public function testGetSeedPathConvertsStringToArray()
    {
       
        $config = new Config($this->getConfigArray());
        $paths = $config->getSeedPaths();

        $this->assertTrue(is_array($paths));
        $this->assertTrue(count($paths) === 1);
    }
}
