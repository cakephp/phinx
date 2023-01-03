<?php

namespace Test\Phinx\Config;

use Phinx\Config\FeatureFlags;
use PHPUnit\Framework\TestCase;

class FeatureFlagsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testSetFlagsFromConfig(): void
    {
        $config = [
            'unsigned_primary_keys' => false,
            'column_null_default' => false,
        ];
        $this->assertTrue(FeatureFlags::$unsignedPrimaryKeys);
        $this->assertTrue(FeatureFlags::$columnNullDefault);
        FeatureFlags::setFlagsFromConfig($config);
        $this->assertFalse(FeatureFlags::$unsignedPrimaryKeys);
        $this->assertFalse(FeatureFlags::$columnNullDefault);
    }
}
