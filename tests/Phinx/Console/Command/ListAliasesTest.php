<?php

namespace Test\Phinx\Console\Command;

use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListAliasesTest extends TestCase
{
    public function provideConfigurations()
    {
        return [
            'JSON config without aliases' => ['valid_config.json', false],
            'JSON config with aliases' => ['valid_config_with_aliases.json', true],
            'PHP config without aliases' => ['valid_config.php', false],
            'PHP config with aliases' => ['valid_config_with_aliases.php', true],
            'YAML config without aliases' => ['seeds.yml', false],
            'YAML config with aliases' => ['seeds_with_aliases.yml', true],
        ];
    }

    /**
     * @param string $file
     * @param bool $hasAliases
     *
     * @dataProvider provideConfigurations
     */
    public function testListingAliases($file, $hasAliases)
    {
        $command = (new PhinxApplication('testing'))->find('list:aliases');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                '--configuration' => realpath(sprintf('%s/../../Config/_files/%s', __DIR__, $file)),
            ],
            ['decorated' => false]
        );

        $display = $commandTester->getDisplay(false);

        if ($hasAliases) {
            $this->assertNotContains('No aliases defined in ', $display);
            $this->assertContains('Alias            Class                                             ', $display);
            $this->assertContains('================ ==================================================', $display);
            $this->assertContains('MakePermission   Vendor\Package\Migration\Creation\MakePermission  ', $display);
            $this->assertContains('RemovePermission Vendor\Package\Migration\Creation\RemovePermission', $display);
        } else {
            $this->assertContains('No aliases defined in ', $display);
        }
    }
}
