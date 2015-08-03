<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigDefaultEnvironmentTest
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getDefaultEnvironment
 */
class ConfigDefaultEnvironmentTest extends AbstractConfigTest
{
    public function testGetDefaultEnvironment()
    {
        // test with the config array
        $configArray = $this->getConfigArray();
        $config = new Config($configArray);
        $this->assertEquals('testing', $config->getDefaultEnvironment());
    }

    public function testConfigReplacesTokensWithEnvVariables()
    {
        $_SERVER['PHINX_DBHOST'] = 'localhost';
        $_SERVER['PHINX_DBNAME'] = 'productionapp';
        $_SERVER['PHINX_DBUSER'] = 'root';
        $_SERVER['PHINX_DBPASS'] = 'ds6xhj1';
        $_SERVER['PHINX_DBPORT'] = '1234';
        $path = __DIR__ . '/_files';
        $config = Config::fromYaml($path . '/external_variables.yml');
        $env = $config->getEnvironment($config->getDefaultEnvironment());
        $this->assertEquals('localhost', $env['host']);
        $this->assertEquals('productionapp', $env['name']);
        $this->assertEquals('root', $env['user']);
        $this->assertEquals('ds6xhj1', $env['pass']);
        $this->assertEquals('1234', $env['port']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The environment configuration (read from $PHINX_ENVIRONMENT) for 'conf-test' is missing
     */
    public function testGetDefaultEnvironmentOverridenByEnvButNotSet()
    {
        // set dummy
        $dummyEnv = 'conf-test';
        putenv('PHINX_ENVIRONMENT=' . $dummyEnv);

        try {
            $config = new Config(array());
            $config->getDefaultEnvironment();
        }
        catch (\Exception $e) {
            // reset back to normal
            putenv('PHINX_ENVIRONMENT=');

            // throw again in order to finish test
            throw $e;
        }
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not find a default environment
     */
    public function testGetDefaultEnvironmentOverridenFailedToFind()
    {
        // set empty env var
        putenv('PHINX_ENVIRONMENT=');

        $config = new Config(array());
        $config->getDefaultEnvironment();
    }
}
