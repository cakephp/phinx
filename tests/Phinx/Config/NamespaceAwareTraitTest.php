<?php

namespace Test\Phinx\Config;

use PHPUnit\Framework\TestCase;

class NamespaceAwareTraitTest extends TestCase
{
    public function testGetMigrationNamespaceByPath()
    {
        $config = $this->getMockForTrait('Phinx\Config\NamespaceAwareTrait');
        $config->expects($this->any())
            ->method('getMigrationPaths')
            ->will($this->returnValue([
                __DIR__ . '/_files',
                'Baz' => __DIR__ . '/_rootDirectories/all/../OnlyPhp',
                'Foo\Bar' => __DIR__ . '/_rootDirectories/all',
            ]));
        /** @var \Phinx\Config\NamespaceAwareTrait $config */
        $this->assertNull($config->getMigrationNamespaceByPath(__DIR__ . '/_files'));
        $this->assertNull($config->getMigrationNamespaceByPath(__DIR__ . '/_rootDirectories/../_files'));
        $this->assertEquals('Baz', $config->getMigrationNamespaceByPath(__DIR__ . '/_rootDirectories/OnlyPhp'));
        $this->assertEquals('Foo\Bar', $config->getMigrationNamespaceByPath(__DIR__ . '/_rootDirectories/all'));
        $this->assertEquals('Foo\Bar', $config->getMigrationNamespaceByPath(__DIR__ . '/_rootDirectories/OnlyPhp/../all'));
    }

    public function testGetSeedNamespaceByPath()
    {
        $config = $this->getMockForTrait('Phinx\Config\NamespaceAwareTrait');
        $config->expects($this->any())
            ->method('getSeedPaths')
            ->will($this->returnValue([
                __DIR__ . '/_files',
                'Baz' => __DIR__ . '/_rootDirectories/all/../OnlyPhp',
                'Foo\Bar' => __DIR__ . '/_rootDirectories/all',
            ]));
        /** @var \Phinx\Config\NamespaceAwareTrait $config */
        $this->assertNull($config->getSeedNamespaceByPath(__DIR__ . '/_files'));
        $this->assertNull($config->getSeedNamespaceByPath(__DIR__ . '/_rootDirectories/../_files'));
        $this->assertEquals('Baz', $config->getSeedNamespaceByPath(__DIR__ . '/_rootDirectories/OnlyPhp'));
        $this->assertEquals('Foo\Bar', $config->getSeedNamespaceByPath(__DIR__ . '/_rootDirectories/all'));
        $this->assertEquals('Foo\Bar', $config->getSeedNamespaceByPath(__DIR__ . '/_rootDirectories/OnlyPhp/../all'));
    }
}
