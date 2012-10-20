<?php

namespace Test\Phinx\Migration;

use Phinx\Migration\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    public function testMapClassNameToFileName()
    {
        $expectedResults = array(
            'CreateUserTable'             => '/^\d{14}_create_user_table\.php$/',
            'LimitResourceNamesTo30Chars' => '/^\d{14}_limit_resource_names_to30_chars\.php$/',
        );
        
        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertRegExp($expectedResult, Util::mapClassNameToFileName($input));
        }
    }
    
    public function testIsValidMigrationClassName()
    {
        $expectedResults = array(
            'CreateUserTable'   => true,
            'test'              => false,
            'Test'              => false
        );
        
        foreach ($expectedResults as $input => $expectedResult) {
            $this->assertEquals($expectedResult, Util::isValidMigrationClassName($input));
        }
    }
}