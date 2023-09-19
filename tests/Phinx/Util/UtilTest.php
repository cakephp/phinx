<?php
declare(strict_types=1);

namespace Test\Phinx\Util;

use DateTime;
use DateTimeZone;
use Phinx\Util\Util;
use RuntimeException;
use Test\Phinx\TestCase;

class UtilTest extends TestCase
{
    private function getCorrectedPath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function testGetExistingMigrationClassNames()
    {
        $expectedResults = [
            'TestMigration',
            'TestMigration2',
        ];

        $existingClassNames = Util::getExistingMigrationClassNames($this->getCorrectedPath(__DIR__ . '/_files/migrations'));
        $this->assertCount(count($expectedResults), $existingClassNames);
        foreach ($expectedResults as $expectedResult) {
            $this->assertContains($expectedResult, $existingClassNames);
        }
    }

    public function testGetExistingMigrationClassNamesWithFile()
    {
        $file = $this->getCorrectedPath(__DIR__ . '/_files/migrations/20120111235330_test_migration.php');
        $existingClassNames = Util::getExistingMigrationClassNames($file);
        $this->assertCount(0, $existingClassNames);
    }

    public function testGetCurrentTimestamp()
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $expected = $dt->format(Util::DATE_FORMAT);

        $current = Util::getCurrentTimestamp();

        // Rather than using a strict equals, we use greater/lessthan checks to
        // prevent false positives when the test hits the edge of a second.
        $this->assertGreaterThanOrEqual($expected, $current);
        // We limit the assertion time to 2 seconds, which should never fail.
        $this->assertLessThanOrEqual($expected + 2, $current);
    }

    public function testGetVersionFromFileName(): void
    {
        $this->assertSame(20221130101652, Util::getVersionFromFileName('20221130101652_test.php'));
    }

    public function testGetVersionFromFileNameErrorNoVersion(): void
    {
        $this->expectException(RuntimeException::class);
        Util::getVersionFromFileName('foo.php');
    }

    public function testGetVersionFromFileNameErrorZeroVersion(): VoidCommand
    {
        $this->expectException(RuntimeException::class);
        Util::getVersionFromFileName('0_foo.php');
    }

    public function providerMapClassNameToFileName(): array
    {
        return [
            ['CamelCase87afterSomeBooze', '/^\d{14}_camel_case_87after_some_booze\.php$/'],
            ['CreateUserTable', '/^\d{14}_create_user_table\.php$/'],
            ['LimitResourceNamesTo30Chars', '/^\d{14}_limit_resource_names_to_30_chars\.php$/'],
        ];
    }

    /**
     * @dataProvider providerMapClassNameToFileName
     */
    public function testMapClassNameToFileName(string $name, string $pattern): void
    {
        $this->assertMatchesRegularExpression($pattern, Util::mapClassNameToFileName($name));
    }

    public function providerMapFileName(): array
    {
        return [
            ['20150902094024_create_user_table.php', 'CreateUserTable'],
            ['20150902102548_my_first_migration2.php', 'MyFirstMigration2'],
            ['20200412012035_camel_case_87after_some_booze.php', 'CamelCase87afterSomeBooze'],
            ['20200412012036_limit_resource_names_to_30_chars.php', 'LimitResourceNamesTo30Chars'],
            ['20200412012037_back_compat_names_to30_chars.php', 'BackCompatNamesTo30Chars'],
            ['20200412012037.php', 'V20200412012037'],
        ];
    }

    /**
     * @dataProvider providerMapFileName
     */
    public function testMapFileNameToClassName(string $fileName, string $className)
    {
        $this->assertEquals($className, Util::mapFileNameToClassName($fileName));
    }

    public function providerValidClassName(): array
    {
        return [
            ['camelCase', false],
            ['CreateUserTable', true],
            ['UserSeeder', true],
            ['Test', true],
            ['test', false],
            ['Q', true],
            ['XMLTriggers', true],
            ['Form_Cards', false],
            ['snake_high_scores', false],
            ['Code2319Incidents', true],
            ['V20200509232007', true],
        ];
    }

    /**
     * @dataProvider providerValidClassName
     */
    public function testIsValidPhinxClassName(string $className, bool $valid): void
    {
        $this->assertSame($valid, Util::isValidPhinxClassName($className));
    }

    public function testGlobPath()
    {
        $files = Util::glob(__DIR__ . '/_files/migrations/empty.txt');
        $this->assertCount(1, $files);
        $this->assertEquals('empty.txt', basename($files[0]));

        $files = Util::glob(__DIR__ . '/_files/migrations/*.php');
        $this->assertCount(3, $files);
        $this->assertEquals('20120111235330_test_migration.php', basename($files[0]));
        $this->assertEquals('20120116183504_test_migration_2.php', basename($files[1]));
        $this->assertEquals('not_a_migration.php', basename($files[2]));
    }

    public function testGlobAll()
    {
        $files = Util::globAll([
            __DIR__ . '/_files/migrations/*.php',
            __DIR__ . '/_files/migrations/subdirectory/*.txt',
        ]);

        $this->assertCount(4, $files);
        $this->assertEquals('20120111235330_test_migration.php', basename($files[0]));
        $this->assertEquals('20120116183504_test_migration_2.php', basename($files[1]));
        $this->assertEquals('not_a_migration.php', basename($files[2]));
        $this->assertEquals('empty.txt', basename($files[3]));
    }

    public function testGetFiles()
    {
        $files = Util::getFiles([
            __DIR__ . '/_files/migrations',
            __DIR__ . '/_files/migrations/subdirectory',
            __DIR__ . '/_files/migrations/subdirectory',
        ]);

        $this->assertCount(4, $files);
        $this->assertEquals('20120111235330_test_migration.php', basename($files[0]));
        $this->assertEquals('20120116183504_test_migration_2.php', basename($files[1]));
        $this->assertEquals('not_a_migration.php', basename($files[2]));
        $this->assertEquals('foobar.php', basename($files[3]));
    }

    /**
     * Returns array of dsn string and expected parsed array.
     *
     * @return array
     */
    public function providerDsnStrings()
    {
        return [
            [
                'mysql://user:pass@host:1234/name?charset=utf8&other_param=value!',
                [
                    'charset' => 'utf8',
                    'other_param' => 'value!',
                    'adapter' => 'mysql',
                    'user' => 'user',
                    'pass' => 'pass',
                    'host' => 'host',
                    'port' => '1234',
                    'name' => 'name',
                ],
            ],
            [
                'pgsql://user:pass@host/name?',
                [
                    'adapter' => 'pgsql',
                    'user' => 'user',
                    'pass' => 'pass',
                    'host' => 'host',
                    'name' => 'name',
                ],
            ],
            [
                'sqlsrv://host:1234/name',
                [
                    'adapter' => 'sqlsrv',
                    'host' => 'host',
                    'port' => '1234',
                    'name' => 'name',
                ],
            ],
            [
                'sqlite://user:pass@host/name',
                [
                    'adapter' => 'sqlite',
                    'user' => 'user',
                    'pass' => 'pass',
                    'host' => 'host',
                    'name' => 'name',
                ],
            ],
            [
                'pgsql://host/name',
                [
                    'adapter' => 'pgsql',
                    'host' => 'host',
                    'name' => 'name',
                ],
            ],
            [
                'pdomock://user:pass!@host/name',
                [
                    'adapter' => 'pdomock',
                    'user' => 'user',
                    'pass' => 'pass!',
                    'host' => 'host',
                    'name' => 'name',
                ],
            ],
            [
                'pdomock://user:pass@host/:1234/name',
                [
                    'adapter' => 'pdomock',
                    'user' => 'user',
                    'pass' => 'pass',
                    'host' => 'host',
                    'name' => ':1234/name',
                ],
            ],
            [
                'pdomock://user:pa:ss@host:1234/name',
                [
                    'adapter' => 'pdomock',
                    'user' => 'user',
                    'pass' => 'pa:ss',
                    'host' => 'host',
                    'port' => '1234',
                    'name' => 'name',
                ],
            ],
            [
                'pdomock://:pass@host:1234/name',
                [
                    'adapter' => 'pdomock',
                    'pass' => 'pass',
                    'host' => 'host',
                    'port' => '1234',
                    'name' => 'name',
                ],
            ],
            [
                'sqlite:///:memory:',
                [
                    'adapter' => 'sqlite',
                    'name' => ':memory:',
                ],
            ],
            ['pdomock://user:pass@host:/name', []],
            ['pdomock://user:pass@:1234/name', []],
            ['://user:pass@host:1234/name', []],
            ['pdomock:/user:p@ss@host:1234/name', []],
        ];
    }

    /**
     * Tests parsing dsn strings.
     *
     * @dataProvider providerDsnStrings
     * @return void
     */
    public function testParseDsn($dsn, $expected)
    {
        $this->assertSame($expected, Util::parseDsn($dsn));
    }
}
