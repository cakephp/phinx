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
                'default_database' => 'development',
                'testing' => array(
                    'adapter' => 'sqllite',
                    'path' => '%%PHINX_CONFIG_PATH%%/testdb/test.db'
                )
            )
        );
    }
    
    /**
     * Tests the <code>getEnvironment</code> method works as expected.
     *
     * @return void
     */
    public function testGetEnvironmentMethodWorksAsExpected()
    {
        $configArray = $this->getConfigArray();
        $config = new \Phinx\Config\Config($configArray);
        $db = $config->getEnvironment('testing');
        $this->assertEquals('sqllite', $db['adapter']);
    }
    
    public function testHasEnvironmentMethodWorksAsExpected()
    {
        $configArray = $this->getConfigArray();
        $config = new \Phinx\Config\Config($configArray);
        $this->assertTrue($config->hasEnvironment('testing'));
        $this->assertFalse($config->hasEnvironment('fakeenvironment'));
    }
    
    public function testGetDefaultEnvironmentMethodWorksAsExpected()
    {
        // TODO - implement
        // test a Yaml file with no 'default_database' key
        // test a Yaml file with no key or entries
        // test a Yaml file with entries only
    }
}
