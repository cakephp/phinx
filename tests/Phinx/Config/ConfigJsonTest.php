<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class ConfigJsonTest
 *
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigJsonTest extends TestCase
{
    /**
     * @covers \Phinx\Config\Config::fromJson
     */
    public function testFromJSONMethod()
    {
        $path = __DIR__ . '/_files';
        $config = Config::fromJson($path . '/valid_config.json');
        $this->assertEquals('dev', $config->getDefaultEnvironment());
    }

    /**
     * @covers \Phinx\Config\Config::fromJson
     */
    public function testFromJSONInvalidJson()
    {
        $path = __DIR__ . '/_files';

        $this->expectException(RuntimeException::class);

        Config::fromJson($path . '/invalid.json');
    }
}
