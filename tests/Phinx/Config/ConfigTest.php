<?php

namespace Test\Phinx\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Returns a sample configuration array for use with the unit tests.
     *
     * @return array
     */
    public function getConfigArray()
    {
        return array(
            'default' => array(
                'paths' => array(
                    'migrations' => '%%PHINX_CONFIG_PATH%%/testmigrations2',
                    'schema' => '%%PHINX_CONFIG_PATH%%/testmigrations2/schema.sql',
                )
            ),
            'environments' => array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'testing',
                'testing' => array(
                    'adapter' => 'sqllite',
                    'path' => '%%PHINX_CONFIG_PATH%%/testdb/test.db'
                ),
                'production' => array(
                    'adapter' => 'mysql'
                )
            )
        );
    }
    
    public function testGetEnvironmentsMethod()
    {
        $config = new \Phinx\Config\Config($this->getConfigArray());
        $this->assertEquals(2, sizeof($config->getEnvironments()));
        $this->assertArrayHasKey('testing', $config->getEnvironments());
        $this->assertArrayHasKey('production', $config->getEnvironments());
    }
    
    public function testGetEnvironmentMethod()
    {
        $config = new \Phinx\Config\Config($this->getConfigArray());
        $db = $config->getEnvironment('testing');
        $this->assertEquals('sqllite', $db['adapter']);
    }
    
    public function testHasEnvironmentMethod()
    {
        $configArray = $this->getConfigArray();
        $config = new \Phinx\Config\Config($configArray);
        $this->assertTrue($config->hasEnvironment('testing'));
        $this->assertFalse($config->hasEnvironment('fakeenvironment'));
    }
    
    public function testGetDefaultEnvironmentMethod()
    {
        $path = __DIR__ . '/_files';
        
        // test with the config array
        $configArray = $this->getConfigArray();
        $config = new \Phinx\Config\Config($configArray);
        $this->assertEquals('testing', $config->getDefaultEnvironment());
        
        // test using a Yaml file without the 'default_database' key.
        // (it should default to the first one).
        $config = \Phinx\Config\Config::fromYaml($path . '/no_default_database_key.yml');
        $this->assertEquals('production', $config->getDefaultEnvironment());
    }
    
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not find a default environment
     */
    public function testGetDefaultEnvironmentWithAnEmptyYamlFile()
    {
        // test using a Yaml file with no key or entries
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromYaml($path . '/empty.yml');
        $config->getDefaultEnvironment();
    }
    
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The environment configuration for 'staging' is missing
     */
    public function testGetDefaultEnvironmentWithAMissingEnvironmentEntry()
    {
        // test using a Yaml file with a 'default_database' key, but without a
        // corresponding entry
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromYaml($path . '/missing_environment_entry.yml');
        $config->getDefaultEnvironment();
    }
    
    public function testGetMigrationPathReturnsNullForNoPath()
    {
        $config = new \Phinx\Config\Config(array());
        $this->assertNull($config->getMigrationPath());
    }
    
    public function testArrayAccessMethods()
    {
        $config = new \Phinx\Config\Config(array());
        $config['foo'] = 'bar';
        $this->assertEquals('bar', $config['foo']);
        $this->assertTrue(isset($config['foo']));
        unset($config['foo']);
        $this->assertFalse(isset($config['foo']));
    }
    
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Identifier "foo" is not defined.
     */
    public function testUndefinedArrayAccess()
    {
        $config = new \Phinx\Config\Config(array());
        $config['foo'];
    }
}