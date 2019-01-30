<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;

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
        $config = new Config([]);
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
        $values = [
            'paths' => [
                'migrations' => '/test'
            ]
        ];

        $config = new Config($values);
        $paths = $config->getMigrationPaths();

        $this->assertInternalType('array', $paths);
        $this->assertCount(1, $paths);
    }
}
