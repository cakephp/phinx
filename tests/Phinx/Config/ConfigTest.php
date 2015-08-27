<?php

namespace Test\Phinx\Config;

use \Phinx\Config\Config;

/**
 * Class ConfigTest
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigTest extends AbstractConfigTest
{
    /**
     * @covers \Phinx\Config\Config::__construct
     * @covers \Phinx\Config\Config::getConfigFilePath
     */
    public function testConstructEmptyArguments()
    {
        $config = new Config(array());
        $this->assertAttributeEmpty('values', $config);
        $this->assertAttributeEquals(null, 'configFilePath', $config);
        $this->assertNull($config->getConfigFilePath());
    }

    /**
     * @covers \Phinx\Config\Config::__construct
     * @covers \Phinx\Config\Config::getConfigFilePath
     */
    public function testConstructByArray()
    {
        $config = new Config($this->getConfigArray());
        $this->assertAttributeNotEmpty('values', $config);
        $this->assertAttributeEquals(null, 'configFilePath', $config);
        $this->assertNull($config->getConfigFilePath());
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
