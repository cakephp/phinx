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

        // test using environment variable PHINX_ENVIRONMENT
        // (it should return the configuration specified in the environment)
        putenv('PHINX_ENVIRONMENT=externally-specified-environment');
        $config = \Phinx\Config\Config::fromYaml($path . '/no_default_database_key.yml');
        $this->assertEquals('externally-specified-environment', $config->getDefaultEnvironment());
        putenv('PHINX_ENVIRONMENT=');
    }

    /**
     * @expectedException \RuntimeException
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

    public function testFromPHPMethod()
    {
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromPhp($path . '/valid_config.php');
        $this->assertEquals('dev', $config->getDefaultEnvironment());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFromPHPMethodWithoutArray()
    {
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromPhp($path . '/config_without_array.php');
        $this->assertEquals('dev', $config->getDefaultEnvironment());
    }

    public function testFromJSONMethod()
    {
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromJson($path . '/valid_config.json');
        $this->assertEquals('dev', $config->getDefaultEnvironment());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFromJSONMethodWithoutJSON()
    {
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromPhp($path . '/empty.json');
        $this->assertEquals('dev', $config->getDefaultEnvironment());
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testGetMigrationPathThrowsExceptionForNoPath()
    {
        $config = new \Phinx\Config\Config(array());
        $config->getMigrationPath();
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

    public function testConfigReplacesTokensWithEnvVariables()
    {
        $_SERVER['PHINX_DBHOST'] = 'localhost';
        $_SERVER['PHINX_DBNAME'] = 'productionapp';
        $_SERVER['PHINX_DBUSER'] = 'root';
        $_SERVER['PHINX_DBPASS'] = 'ds6xhj1';
        $_SERVER['PHINX_DBPORT'] = '1234';
        $path = __DIR__ . '/_files';
        $config = \Phinx\Config\Config::fromYaml($path . '/external_variables.yml');
        $env = $config->getEnvironment($config->getDefaultEnvironment());
        $this->assertEquals('localhost', $env['host']);
        $this->assertEquals('productionapp', $env['name']);
        $this->assertEquals('root', $env['user']);
        $this->assertEquals('ds6xhj1', $env['pass']);
        $this->assertEquals('1234', $env['port']);
    }

    public function testGetMigrationBaseClassNameGetsDefaultBaseClass()
    {
        $config = new \Phinx\Config\Config(array());
        $this->assertEquals('AbstractMigration', $config->getMigrationBaseClassName());
    }

    public function testGetMigrationBaseClassNameGetsDefaultBaseClassWithNamespace()
    {
        $config = new \Phinx\Config\Config(array());
        $this->assertEquals('Phinx\Migration\AbstractMigration', $config->getMigrationBaseClassName(false));
    }

    public function testGetMigrationBaseClassNameGetsAlternativeBaseClass()
    {
        $config = new \Phinx\Config\Config(array('migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration'));
        $this->assertEquals('AlternativeAbstractMigration', $config->getMigrationBaseClassName());
    }

    public function testGetMigrationBaseClassNameGetsAlternativeBaseClassWithNamespace()
    {
        $config = new \Phinx\Config\Config(array('migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration'));
        $this->assertEquals('Phinx\Migration\AlternativeAbstractMigration', $config->getMigrationBaseClassName(false));
    }
}
