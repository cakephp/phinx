<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigYamlTest
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigYamlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers \Phinx\Config\Config::fromYaml
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     * @expectedException \RuntimeException
     */
    public function testGetDefaultEnvironmentWithAnEmptyYamlFile()
    {
        // test using a Yaml file with no key or entries
        $path = __DIR__ . '/_files';
        $config = Config::fromYaml($path . '/empty.yml');
        $config->getDefaultEnvironment();
    }

    /**
     * @covers \Phinx\Config\Config::fromYaml
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The environment configuration for 'staging' is missing
     */
    public function testGetDefaultEnvironmentWithAMissingEnvironmentEntry()
    {
        // test using a Yaml file with a 'default_database' key, but without a
        // corresponding entry
        $path = __DIR__ . '/_files';
        $config = Config::fromYaml($path . '/missing_environment_entry.yml');
        $config->getDefaultEnvironment();
    }

    /**
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentMethod()
    {
        $path = __DIR__ . '/_files';

        // test using a Yaml file without the 'default_database' key.
        // (it should default to the first one).
        $config = Config::fromYaml($path . '/no_default_database_key.yml');
        $this->assertEquals('production', $config->getDefaultEnvironment());

        // test using environment variable PHINX_ENVIRONMENT
        // (it should return the configuration specified in the environment)
        putenv('PHINX_ENVIRONMENT=externally-specified-environment');
        $config = Config::fromYaml($path . '/no_default_database_key.yml');
        $this->assertEquals('externally-specified-environment', $config->getDefaultEnvironment());
        putenv('PHINX_ENVIRONMENT=');
    }
}