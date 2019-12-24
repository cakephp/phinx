<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigMigrationPathsTest
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getMigrationPaths
 */
class ConfigMigrationPathsTest extends AbstractConfigTest
{
    public function testGetMigrationPathsThrowsExceptionForNoPath()
    {
        $this->expectException(UnexpectedValueException::class);
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
