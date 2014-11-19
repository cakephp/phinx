<?php

namespace Test\Phinx\Migration;

use Phinx\Migration\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
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

        // with timestamp
        $expectedResults = array(
            'CamelCase87afterSomeBooze_20141231102030'   => '/^\d{14}_camel_case87after_some_booze\.php$/',
            'CreateUserTable_20141231102030'             => '/^\d{14}_create_user_table\.php$/',
            'LimitResourceNamesTo30Chars_20141231102030' => '/^\d{14}_limit_resource_names_to30_chars\.php$/',
        );

        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertRegExp($expectedResult, Util::mapClassNameToFileName($input, true));
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

    public static function generateClassNameDataProvider()
    {
        $timestamp = '\d{14}';
        return array(
            array('123', false, false),
            array('123', true, false),
            array('abc123', false, '/^Abc123$/'),
            array('abc123', true, '/^Abc123_' . $timestamp . '$/'),
            array('abc', false, '/^Abc$/'),
            array('abc', true, '/^Abc_' . $timestamp . '$/'),
            array('abcDef', false, '/^AbcDef$/'),
            array('abcDef', true, '/^AbcDef_' . $timestamp . '$/'),
        );
    }

    /**
     *
     * @param string  $name           Class name
     * @param boolean $autoTimestamp  Auto timestamp class names is enabled?
     * @param boolean $expectedResult String or false if an exception is expected (invalid argument)
     *
     * @dataProvider generateClassNameDataProvider
     */
    public function testGenerateClassName($name, $autoTimestamp, $expectedResult)
    {
        try {
            $result = Util::generateClassName($name, $autoTimestamp);
            if ($expectedResult === false) {
                self::fail('Expected exception but not thrown');
            }
            self::assertRegExp($expectedResult, $result);
        } catch (\InvalidArgumentException $ex) {
            if ($expectedResult !== false) {
                self::fail('Unexpected exception: '.$ex->getMessage);
            }
        }
    }


    public static function mapFileNameToClassNameDataProvider()
    {
        $timestamp = '20141231102030';
        return array(
            array($timestamp . '_abc.php', false, '/^Abc$/'),
            array($timestamp . '_abc.php', true, '/^Abc_\d{14}$/'),
            array($timestamp . '_abc_def.php', false, '/^AbcDef$/'),
            array($timestamp . '_abc_def.php', true, '/^AbcDef_\d{14}$/'),
            array($timestamp . '_abc123_def.php', false, '/^Abc123Def$/'),
            array($timestamp . '_abc123_def.php', true, '/^Abc123Def_\d{14}$/'),
        );
    }

    /**
     *
     * @param string  $fileName       File name
     * @param boolean $autoTimestamp  Auto timestamp class names is enabled?
     * @param boolean $expectedResult String or false if an exception is expected (invalid argument)
     *
     * @dataProvider mapFileNameToClassNameDataProvider
     */
    public function testMapFileNameToClassName($fileName, $autoTimestamp, $expectedResult)
    {
        try {
            $result = Util::mapFileNameToClassName($fileName, $autoTimestamp);
            if ($expectedResult === false) {
                self::fail('Expected exception but not thrown');
            }
            self::assertRegExp($expectedResult, $result);
        } catch (\InvalidArgumentException $ex) {
            if ($expectedResult !== false) {
                self::fail('Unexpected exception: '.$ex->getMessage);
            }
        }
    }

}
