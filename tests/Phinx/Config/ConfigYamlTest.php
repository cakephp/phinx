<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class ConfigYamlTest
 *
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigYamlTest extends TestCase
{
    /**
     * @covers \Phinx\Config\Config::fromYaml
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentWithAnEmptyYamlFile()
    {
        // test using a Yaml file with no key or entries
        $path = __DIR__ . '/_files';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/File '.*\/empty.yml' must be valid YAML/");

        Config::fromYaml($path . '/empty.yml');
    }

    /**
     * @covers \Phinx\Config\Config::fromYaml
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentWithAMissingEnvironmentEntry()
    {
        // test using a Yaml file with a 'default_environment' key, but without a
        // corresponding entry
        $path = __DIR__ . '/_files';
        $config = Config::fromYaml($path . '/missing_environment_entry.yml');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The environment configuration for 'staging' is missing");

        $config->getDefaultEnvironment();
    }

    /**
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentMethod()
    {
        $path = __DIR__ . '/_files';

        // test using a Yaml file without the 'default_environment' key.
        // (it should default to the first one).
        $config = Config::fromYaml($path . '/no_default_environment_key.yml');
        $this->assertEquals('production', $config->getDefaultEnvironment());

        // test using environment variable PHINX_ENVIRONMENT
        // (it should return the configuration specified in the environment)
        putenv('PHINX_ENVIRONMENT=externally-specified-environment');
        $config = Config::fromYaml($path . '/no_default_environment_key.yml');
        $this->assertEquals('externally-specified-environment', $config->getDefaultEnvironment());
        putenv('PHINX_ENVIRONMENT=');
    }
}
