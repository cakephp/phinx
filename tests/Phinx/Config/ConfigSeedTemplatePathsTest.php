<?php

namespace Test\Phinx\Config;

use Phinx\Config\Config;

/**
 * Class ConfigSeedTemplatePathsTest
 *
 * @package Test\Phinx\Config
 * @group config
 * @covers \Phinx\Config\Config::getSeederTemplateFile
 */
class ConfigSeedTemplatePathsTest extends AbstractConfigTest
{
    public function testTemplateAndPathAreSet()
    {
        $values = [
            'paths' => [
                'seeds' => '/test',
            ],
            'templates' => [
                'seedTemplateFile' => 'seedTemplateFilePath',
            ],
        ];

        $config = new Config($values);

        $actualValue = $config->getSeederTemplateFile();
        $this->assertEquals('seedTemplateFilePath', $actualValue);
    }

    public function testTemplateIsSetButNoPath()
    {
        // Here is used another key just to keep the node 'template' not empty
        $values = [
            'paths' => [
                'seeds' => '/test',
            ],
            'templates' => [
                'file' => 'migration_template_file',
            ],
        ];

        $config = new Config($values);

        $actualValue = $config->getSeederTemplateFile();
        $this->assertNull($actualValue);
    }

    public function testNoCustomSeedTemplate()
    {
        $values = [
            'paths' => [
                'seeds' => '/test',
            ],
        ];
        $config = new Config($values);

        $actualValue = $config->getSeederTemplateFile();
        $this->assertNull($actualValue);

        $config->getSeedPaths();
    }
}
