<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class ConfigPhpTest
 *
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigPhpTest extends TestCase
{
    /**
     * @covers \Phinx\Config\Config::fromPhp
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testFromPHPMethod()
    {
        $path = __DIR__ . '/_files';
        $config = Config::fromPhp($path . '/valid_config.php');
        $this->assertEquals('dev', $config->getDefaultEnvironment());
    }

    /**
     * @covers \Phinx\Config\Config::fromPhp
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testFromPHPMethodWithoutArray()
    {
        $path = __DIR__ . '/_files';

        $this->expectException(RuntimeException::class);
        Config::fromPhp($path . '/config_without_array.php');
    }

    /**
     * @covers \Phinx\Config\Config::fromPhp
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testFromJSONMethodWithoutJSON()
    {
        $path = __DIR__ . '/_files';

        $this->expectException(RuntimeException::class);
        Config::fromPhp($path . '/empty.json');
    }
}
