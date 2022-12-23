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
            'unsigned_pks' => false,
            'column_null' => false,
        ];
        $this->assertTrue(FeatureFlags::$unsignedPks);
        $this->assertTrue(FeatureFlags::$columnNull);
        FeatureFlags::setFlagsFromConfig($config);
        $this->assertFalse(FeatureFlags::$unsignedPks);
        $this->assertFalse(FeatureFlags::$columnNull);
    }
}
