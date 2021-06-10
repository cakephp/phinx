<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigSeedPathsTest
 *
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getSeedPaths
 */
class ConfigSeedPathsTest extends AbstractConfigTest
{
    public function testGetSeedPathsThrowsExceptionForNoPath()
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);

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
        $values = [
            'paths' => [
                'seeds' => '/test',
            ],
        ];

        $config = new Config($values);
        $paths = $config->getSeedPaths();

        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);
    }
}
