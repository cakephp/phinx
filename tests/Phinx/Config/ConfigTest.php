<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigTest
 * @package Test\Phinx\Config
 * @group config
 */
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

    /**
     * @covers \Phinx\Config\Config::__construct
     */
    public function testConstructEmptyArguments()
    {
        $config = new Config(array());
        $this->assertAttributeEmpty('values', $config);
        $this->assertAttributeEquals(null, 'configFilePath', $config);
    }

    /**
     * @covers \Phinx\Config\Config::__construct
     */
    public function testConstructByArray()
    {
        $config = new Config($this->getConfigArray());
        $this->assertAttributeNotEmpty('values', $config);
        $this->assertAttributeEquals(null, 'configFilePath', $config);
    }

    /**
     * @covers \Phinx\Config\Config::getEnvironments
     */
    public function testGetEnvironmentsMethod()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals(2, count($config->getEnvironments()));
        $this->assertArrayHasKey('testing', $config->getEnvironments());
        $this->assertArrayHasKey('production', $config->getEnvironments());
    }

    /**
     * @covers \Phinx\Config\Config::hasEnvironment
     */
    public function testHasEnvironmentDoesntHave()
    {
        $config = new Config(array());
        $this->assertFalse($config->hasEnvironment('dummy'));
    }

    /**
     * @covers \Phinx\Config\Config::hasEnvironment
     */
    public function testHasEnvironmentHasOne()
    {
        $config = new Config($this->getConfigArray());
        $this->assertTrue($config->hasEnvironment('testing'));
    }

    /**
     * @covers \Phinx\Config\Config::getEnvironments
     */
    public function testGetEnvironmentsNotSet()
    {
        $config = new Config(array());
        $this->assertNull($config->getEnvironments());
    }

    /**
     * @covers \Phinx\Config\Config::getEnvironment
     */
    public function testGetEnvironmentMethod()
    {
        $config = new Config($this->getConfigArray());
        $db = $config->getEnvironment('testing');
        $this->assertEquals('sqllite', $db['adapter']);
    }

    /**
     * @covers \Phinx\Config\Config::getEnvironment
     */
    public function testHasEnvironmentMethod()
    {
        $configArray = $this->getConfigArray();
        $config = new Config($configArray);
        $this->assertTrue($config->hasEnvironment('testing'));
        $this->assertFalse($config->hasEnvironment('fakeenvironment'));
    }

    /**
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentMethod()
    {
        // test with the config array
        $configArray = $this->getConfigArray();
        $config = new Config($configArray);
        $this->assertEquals('testing', $config->getDefaultEnvironment());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationPath
     * @expectedException \UnexpectedValueException
     */
    public function testGetMigrationPathThrowsExceptionForNoPath()
    {
        $config = new Config(array());
        $config->getMigrationPath();
    }

    /**
     * @covers \Phinx\Config\Config::offsetGet
     * @covers \Phinx\Config\Config::offsetSet
     * @covers \Phinx\Config\Config::offsetExists
     * @covers \Phinx\Config\Config::offsetUnset
     */
    public function testArrayAccessMethods()
    {
        $config = new Config(array());
        $config['foo'] = 'bar';
        $this->assertEquals('bar', $config['foo']);
        $this->assertTrue(isset($config['foo']));
        unset($config['foo']);
        $this->assertFalse(isset($config['foo']));
    }

    /**
     * @covers \Phinx\Config\Config::offsetGet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Identifier "foo" is not defined.
     */
    public function testUndefinedArrayAccess()
    {
        $config = new Config(array());
        $config['foo'];
    }

    /**
     * @covers \Phinx\Config\Config::fromYaml
     * @covers \Phinx\Config\Config::getEnvironment
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
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
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsDefaultBaseClass()
    {
        $config = new Config(array());
        $this->assertEquals('AbstractMigration', $config->getMigrationBaseClassName());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsDefaultBaseClassWithNamespace()
    {
        $config = new Config(array());
        $this->assertEquals('Phinx\Migration\AbstractMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsAlternativeBaseClass()
    {
        $config = new Config(array('migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration'));
        $this->assertEquals('AlternativeAbstractMigration', $config->getMigrationBaseClassName());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsAlternativeBaseClassWithNamespace()
    {
        $config = new Config(array('migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration'));
        $this->assertEquals('Phinx\Migration\AlternativeAbstractMigration', $config->getMigrationBaseClassName(false));
    }
}
