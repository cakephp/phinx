<?php

namespace Test\Phinx\Config;

use InvalidArgumentException;
use Phinx\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigTest
 *
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigTest extends AbstractConfigTest
{
    /**
     * @covers \Phinx\Config\Config::getEnvironments
     */
    public function testGetEnvironmentsMethod()
    {
        $config = new Config($this->getConfigArray());
        $this->assertCount(2, $config->getEnvironments());
        $this->assertArrayHasKey('testing', $config->getEnvironments());
        $this->assertArrayHasKey('production', $config->getEnvironments());
    }

    /**
     * @covers \Phinx\Config\Config::hasEnvironment
     */
    public function testHasEnvironmentDoesntHave()
    {
        $config = new Config([]);
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
        $config = new Config([]);
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
     * @covers \Phinx\Config\Config::getDataDomain
     */
    public function testGetDataDomainMethod()
    {
        $config = new Config($this->getConfigArray());
        $this->assertIsArray($config->getDataDomain());
    }

    /**
     * @covers \Phinx\Config\Config::getDataDomain
     */
    public function testReturnsEmptyArrayWithEmptyDataDomain()
    {
        $config = new Config([]);
        $this->assertIsArray($config->getDataDomain());
        $this->assertCount(0, $config->getDataDomain());
    }

    /**
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentUsingDatabaseKey()
    {
        $configArray = $this->getConfigArray();
        $configArray['environments']['default_environment'] = 'production';
        $config = new Config($configArray);
        $this->assertEquals('production', $config->getDefaultEnvironment());
    }

    /**
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testGetDefaultEnvironmentUsingDefaultDatabase()
    {
        $configArray = $this->getConfigArray();
        $configArray['environments']['default_database'] = 'production';
        $config = new Config($configArray);

        $errorReporting = error_reporting();
        try {
            error_reporting(E_ALL ^ E_USER_DEPRECATED);
            $this->assertEquals('production', $config->getDefaultEnvironment());
        } finally {
            error_reporting($errorReporting);
        }
    }

    /**
     * @covers \Phinx\Config\Config::getDefaultEnvironment
     */
    public function testDefaultDatabaseThrowsDeprecatedNotice()
    {
        $configArray = $this->getConfigArray();
        $configArray['environments']['default_database'] = 'production';
        $config = new Config($configArray);

        $this->expectDeprecation();
        $this->expectExceptionMessage('default_database in the config has been deprecated since 0.12, use default_environment instead.');
        $config->getDefaultEnvironment();
    }

    public function testEnvironmentHasMigrationTable()
    {
        $configArray = $this->getConfigArray();
        $configArray['environments']['production']['migration_table'] = 'test_table';
        $config = new Config($configArray);

        $this->assertSame('phinxlog', $config->getEnvironment('testing')['migration_table']);
        $this->assertSame('test_table', $config->getEnvironment('production')['migration_table']);
    }

    /**
     * @covers \Phinx\Config\Config::offsetGet
     * @covers \Phinx\Config\Config::offsetSet
     * @covers \Phinx\Config\Config::offsetExists
     * @covers \Phinx\Config\Config::offsetUnset
     */
    public function testArrayAccessMethods()
    {
        $config = new Config([]);
        $config['foo'] = 'bar';
        $this->assertEquals('bar', $config['foo']);
        $this->assertArrayHasKey('foo', $config);
        unset($config['foo']);
        $this->assertArrayNotHasKey('foo', $config);
    }

    /**
     * @covers \Phinx\Config\Config::offsetGet
     */
    public function testUndefinedArrayAccess()
    {
        $config = new Config([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier "foo" is not defined.');

        $config['foo'];
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsDefaultBaseClass()
    {
        $config = new Config([]);
        $this->assertEquals('AbstractMigration', $config->getMigrationBaseClassName());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsDefaultBaseClassWithNamespace()
    {
        $config = new Config([]);
        $this->assertEquals('Phinx\Migration\AbstractMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsAlternativeBaseClass()
    {
        $config = new Config(['migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration']);
        $this->assertEquals('AlternativeAbstractMigration', $config->getMigrationBaseClassName());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsAlternativeBaseClassWithNamespace()
    {
        $config = new Config(['migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration']);
        $this->assertEquals('Phinx\Migration\AlternativeAbstractMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getTemplateFile
     * @covers \Phinx\Config\Config::getTemplateClass
     */
    public function testGetTemplateValuesFalseOnEmpty()
    {
        $config = new Config([]);
        $this->assertFalse($config->getTemplateFile());
        $this->assertFalse($config->getTemplateClass());
    }

    public function testGetAliasNoAliasesEntry()
    {
        $config = new Config([]);
        $this->assertNull($config->getAlias('Short'));
    }

    public function testGetAliasEmptyAliasesEntry()
    {
        $config = new Config(['aliases' => []]);
        $this->assertNull($config->getAlias('Short'));
    }

    public function testGetAliasInvalidAliasRequest()
    {
        $config = new Config(['aliases' => ['Medium' => 'Some\Long\Classname']]);
        $this->assertNull($config->getAlias('Short'));
    }

    public function testGetAliasValidAliasRequest()
    {
        $config = new Config(['aliases' => ['Short' => 'Some\Long\Classname']]);
        $this->assertEquals('Some\Long\Classname', $config->getAlias('Short'));
    }

    public function testGetSeedPath()
    {
        $config = new Config(['paths' => ['seeds' => 'db/seeds']]);
        $this->assertEquals(['db/seeds'], $config->getSeedPaths());

        $config = new Config(['paths' => ['seeds' => ['db/seeds1', 'db/seeds2']]]);
        $this->assertEquals(['db/seeds1', 'db/seeds2'], $config->getSeedPaths());
    }

    /**
     * @covers \Phinx\Config\Config::getSeedPaths
     */
    public function testGetSeedPathThrowsException()
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Seeds path missing from config file');

        $config->getSeedPaths();
    }

    /**
     * Checks if base class is returned correctly when specified without
     * a namespace.
     *
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameNoNamespace()
    {
        $config = new Config(['migration_base_class' => 'BaseMigration']);
        $this->assertEquals('BaseMigration', $config->getMigrationBaseClassName());
    }

    /**
     * Checks if base class is returned correctly when specified without
     * a namespace.
     *
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameNoNamespaceNoDrop()
    {
        $config = new Config(['migration_base_class' => 'BaseMigration']);
        $this->assertEquals('BaseMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getVersionOrder
     */
    public function testGetVersionOrder()
    {
        $config = new Config([]);
        $config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $this->assertEquals(Config::VERSION_ORDER_EXECUTION_TIME, $config->getVersionOrder());
    }

    /**
     * @covers \Phinx\Config\Config::isVersionOrderCreationTime
     * @dataProvider isVersionOrderCreationTimeDataProvider
     */
    public function testIsVersionOrderCreationTime($versionOrder, $expected)
    {
        // get config stub
        $configStub = $this->getMockBuilder('\Phinx\Config\Config')
            ->setMethods(['getVersionOrder'])
            ->setConstructorArgs([[]])
            ->getMock();

        $configStub->expects($this->once())
            ->method('getVersionOrder')
            ->will($this->returnValue($versionOrder));

        $this->assertEquals($expected, $configStub->isVersionOrderCreationTime());
    }

    /**
     * @covers \Phinx\Config\Config::isVersionOrderCreationTime
     */
    public function isVersionOrderCreationTimeDataProvider()
    {
        return [
            'With Creation Time Version Order' =>
            [
                Config::VERSION_ORDER_CREATION_TIME, true,
            ],
            'With Execution Time Version Order' =>
            [
                Config::VERSION_ORDER_EXECUTION_TIME, false,
            ],
        ];
    }

    public function testConfigReplacesEnvironmentTokens()
    {
        $_SERVER['PHINX_TEST_CONFIG_ADAPTER'] = 'sqlite';
        $_SERVER['PHINX_TEST_CONFIG_SUFFIX'] = 'sqlite3';
        $_ENV['PHINX_TEST_CONFIG_NAME'] = 'phinx_testing';
        $_ENV['PHINX_TEST_CONFIG_SUFFIX'] = 'foo';

        try {
            $config = new Config([
                'environments' => [
                    'production' => [
                        'adapter' => '%%PHINX_TEST_CONFIG_ADAPTER%%',
                        'name' => '%%PHINX_TEST_CONFIG_NAME%%',
                        'suffix' => '%%PHINX_TEST_CONFIG_SUFFIX%%',
                    ],
                ],
            ]);

            $this->assertSame(
                ['adapter' => 'sqlite', 'name' => 'phinx_testing', 'suffix' => 'sqlite3'],
                $config->getEnvironment('production')
            );
        } finally {
            unset($_SERVER['PHINX_TEST_CONFIG_ADAPTER']);
            unset($_SERVER['PHINX_TEST_CONFIG_SUFFIX']);
            unset($_ENV['PHINX_TEST_CONFIG_NAME']);
            unset($_ENV['PHINX_TEST_CONFIG_SUFFIX']);
        }
    }

    public function testSqliteMemorySetsName()
    {
        $config = new Config([
            'environments' => [
                'production' => [
                    'adapter' => 'sqlite',
                    'memory' => true,
                ],
            ],
        ]);
        $this->assertSame(
            ['adapter' => 'sqlite', 'memory' => true, 'name' => ':memory:'],
            $config->getEnvironment('production')
        );
    }

    public function testSqliteMemoryOverridesName()
    {
        $config = new Config([
            'environments' => [
                'production' => [
                    'adapter' => 'sqlite',
                    'memory' => true,
                    'name' => 'blah',
                ],
            ],
        ]);
        $this->assertSame(
            ['adapter' => 'sqlite', 'memory' => true, 'name' => ':memory:'],
            $config->getEnvironment('production')
        );
    }

    public function testSqliteNonBooleanMemory()
    {
        $config = new Config([
            'environments' => [
                'production' => [
                    'adapter' => 'sqlite',
                    'memory' => 'yes',
                ],
            ],
        ]);
        $this->assertSame(
            ['adapter' => 'sqlite', 'memory' => 'yes', 'name' => ':memory:'],
            $config->getEnvironment('production')
        );
    }

    public function testDefaultTemplateStyle(): void
    {
        $config = new Config([]);
        $this->assertSame('change', $config->getTemplateStyle());
    }

    public function templateStyleDataProvider(): array
    {
        return [
            ['change', 'change'],
            ['up_down', 'up_down'],
            ['foo', 'change'],
        ];
    }

    /**
     * @dataProvider templateStyleDataProvider
     */
    public function testTemplateStyle(string $style, string $expected): void
    {
        $config = new Config(['templates' => ['style' => $style]]);
        $this->assertSame($expected, $config->getTemplateStyle());
    }
}
