<?php

namespace Test\Phinx\Migration;

use Phinx\Migration\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    public function testGetCurrentTimestamp()
    {
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        $expected = $dt->format(Util::DATE_FORMAT);

        $util = new Util();
        $current = $util->getCurrentTimestamp();

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

        $util = new Util();
        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertRegExp($expectedResult, $util->mapClassNameToFileName($input));
        }
    }

    public function testCheckMigrationClassName()
    {
        $path = '/path/to/migrations/';
        $validClassName = array('CreateUserTable', 'Test');

        $util = new Util();
        foreach ($validClassName as $className) {
            // Expected: No exception thrown
            $this->assertNull($util->checkMigrationClassName($className, $path));
        }
    }

    public function providerInvalidClassName()
    {
        return array(
            array('CAmelCase'),
            array('test'),
        );
    }

    /**
     * @dataProvider providerInvalidClassName
     * @param $className
     */
    public function testCheckMigrationClassNameThrowException($className)
    {
        $this->setExpectedException('InvalidArgumentException');
        $path = '/path/to/migrations/';

        $util = new Util();
        $this->assertNull($util->checkMigrationClassName($className, $path));
    }

    public function testCheckMigrationClassNameCatchDuplication()
    {
        $path = '/path/to/migrations/';
        $utilMockClass = $this->getMockBuilder('Phinx\Migration\Util')
            ->setMethods(array('getCurrentMigrationClassNames'))
            ->getMock();

        $utilMockClass->expects($this->any())
            ->method('getCurrentMigrationClassNames')
            ->will($this->returnValue(array('PostRepository')))->with($path);

        $this->setExpectedException('InvalidArgumentException');
        $utilMockClass->checkMigrationClassName('PostRepository', $path);
    }

    public function testMapFileNameToClassName()
    {
        $util = new Util();

        $expectedResults = array(
            '123456789_camel_case87after_some_booze.php'    => 'CamelCase87afterSomeBooze',
            '123456789_create_user_table.php'               => 'CreateUserTable',
            '123456789_limit_resource_names_to30_chars.php' => 'LimitResourceNamesTo30Chars',
        );

        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertEquals($expectedResult, $util->mapFileNameToClassName($input));
        }
    }
}
