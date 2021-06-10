<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;

/**
 * Class ConfigDsnTest
 *
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getEnvironment
 */
class ConfigDsnTest extends AbstractConfigTest
{
    public function testConnectionOptionsCanBeSpecifiedWithDsn()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database';
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertArrayHasKey('adapter', $options);
        $this->assertSame('pdomock', $options['adapter']);
        $this->assertArrayHasKey('user', $options);
        $this->assertSame('phinx', $options['user']);
        $this->assertArrayHasKey('pass', $options);
        $this->assertSame('supersecret', $options['pass']);
        $this->assertArrayHasKey('host', $options);
        $this->assertSame('my-database-host', $options['host']);
        $this->assertArrayHasKey('port', $options);
        $this->assertEquals(1234, $options['port']);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('my_app_database', $options['name']);
    }

    public function testDsnOnlySetsSpecifiedOptions()
    {
        $dsn = 'pdomock://my-database-host/my_app_database';
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertArrayNotHasKey('user', $options);
        $this->assertArrayNotHasKey('pass', $options);
        $this->assertArrayNotHasKey('port', $options);
    }

    public function testDsnGetsRemovedWhenAfterSuccessfulParsing()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database';
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $this->assertArrayNotHasKey('dsn', $cfg->getEnvironment('testenv'));
    }

    public function testOptionsAreLeftAsIsOnInvalidDsn()
    {
        $dsn = 'pdomock://phinx:supersecret@localhost:12badport34/db_name';
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertArrayHasKey('dsn', $options);
        $this->assertSame($dsn, $options['dsn']);
    }

    public function testDsnDoesNotOverrideSpecifiedOptions()
    {
        $dsn = 'pdomock://my-database-host:1234/my_web_database';
        $cfg = new Config(['environments' => ['testenv' => [
            'user' => 'api_user',
            'dsn' => $dsn,
            'name' => 'my_api_database',
        ]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertArrayHasKey('user', $options);
        $this->assertSame('api_user', $options['user']);
        $this->assertArrayNotHasKey('pass', $options);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('my_api_database', $options['name']);
    }

    public function testNoModificationToOptionsOnInvalidDsn()
    {
        $dsn = 'pdomock://phinx:supersecret@localhost:12badport34/db_name';
        $cfg = new Config(['environments' => ['testenv' => [
            'user' => 'api_user',
            'dsn' => $dsn,
            'name' => 'my_api_database',
        ]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertArrayHasKey('user', $options);
        $this->assertSame('api_user', $options['user']);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('my_api_database', $options['name']);
        $this->assertArrayHasKey('dsn', $options);
    }

    public function testDsnQueryProvidesAdditionalOptions()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database?charset=utf8&unrelated=thing&';
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertArrayHasKey('charset', $options);
        $this->assertSame('utf8', $options['charset']);
        $this->assertArrayHasKey('unrelated', $options);
        $this->assertSame('thing', $options['unrelated']);
        $this->assertArrayNotHasKey('query', $options);
    }

    public function testDsnQueryDoesNotOverrideDsnParameters()
    {
        $dsn = 'pdomock://phinx:supersecret@my-database-host:1234/my_app_database?port=80&host=another-host';
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $options = $cfg->getEnvironment('testenv');
        $this->assertSame('my-database-host', $options['host']);
        $this->assertEquals(1234, $options['port']);
    }

    public function dataProviderValidDsn()
    {
        return [
            ['mysql://user:pass@host:1234/name?charset=utf8'],
            ['postgres://user:pass@host/name?'],
            ['mssql://user:@host:1234/name'],
            ['sqlite3://user@host:1234/name'],
            ['sqlite3:///:memory:'],
            ['pdomock://host:1234/name'],
            ['pdomock://user:pass@host/name'],
            ['pdomock://host/name'],
            ['pdomock://user:pass@host/:1234/name'],
            ['pdomock://user:pa:ss@host:1234/name'],
            ['pdomock://user:pass@host:1234/ '],
            ['pdomock://:pass@host:1234/name'],
            ['pdomock://user:pass@host:01234/name'],
        ];
    }

    public function dataProviderInvalidDsn()
    {
        return [
            ['pdomock://user:pass@host:/name'],
            ['pdomock://user:pass@:1234/name'],
            ['://user:pass@host:1234/name'],
            ['pdomock:/user:p@ss@host:1234/name'],
            ['pdomock://user:pass@host:/1234name'],
        ];
    }

    /**
     * @dataProvider \Test\Phinx\Config\ConfigDsnTest::dataProviderValidDsn()
     */
    public function testValidDsn($dsn)
    {
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $this->assertArrayNotHasKey('dsn', $cfg->getEnvironment('testenv'));
    }

    /**
     * @dataProvider \Test\Phinx\Config\ConfigDsnTest::dataProviderInvalidDsn()
     */
    public function testInvalidDsn($dsn)
    {
        $cfg = new Config(['environments' => ['testenv' => ['dsn' => $dsn]]]);
        $this->assertArrayHasKey('dsn', $cfg->getEnvironment('testenv'));
    }
}
