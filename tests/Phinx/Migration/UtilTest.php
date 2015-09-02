<?php

namespace Test\Phinx\Migration;

use Phinx\Migration\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    private function getCorrectedPath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function testGetExistingMigrationClassNames()
    {
        $expectedResults = array(
            'TestMigration',
            'TestMigration2',
        );

        $existingClassNames = Util::getExistingMigrationClassNames($this->getCorrectedPath(__DIR__ . '/_files/migrations'));
        $this->assertCount(count($expectedResults), $existingClassNames);
        foreach ($expectedResults as $expectedResult) {
            $this->arrayHasKey($expectedResult, $existingClassNames);
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
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        $expected = $dt->format(Util::DATE_FORMAT);

        $current = Util::getCurrentTimestamp();

        // Rather than using a strict equals, we use greater/lessthan checks to
        // prevent false positives when the test hits the edge of a second.
        $this->assertGreaterThanOrEqual($expected, $current);
        // We limit the assertion time to 2 seconds, which should never fail.
        $this->assertLessThanOrEqual($expected + 2, $current);
    }

    public function testMapClassNameToFileName()
    {
        $expectedResults = array(
            'CamelCase87afterSomeBooze'   => '/^\d{14}_camel_case87after_some_booze\.php$/',
            'CreateUserTable'             => '/^\d{14}_create_user_table\.php$/',
            'LimitResourceNamesTo30Chars' => '/^\d{14}_limit_resource_names_to30_chars\.php$/',
        );

        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertRegExp($expectedResult, Util::mapClassNameToFileName($input));
        }
    }

    public function testMapFileNameToClassName()
    {
        $expectedResults = array(
            '20150902094024_create_user_table.php'    => 'CreateUserTable',
            '20150902102548_my_first_migration2.php'  => 'MyFirstMigration2',
        );

        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertEquals($expectedResult, Util::mapFileNameToClassName($input));
        }
    }

    public function testIsValidMigrationClassName()
    {
        $expectedResults = array(
            'CAmelCase'         => false,
            'CreateUserTable'   => true,
            'Test'              => true,
            'test'              => false
        );

        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertEquals($expectedResult, Util::isValidMigrationClassName($input));
        }
    }
}
