<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Command;

use Phinx\Console\Command\AbstractCommand;
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
     * @dataProvider provideConfigurations
     */
    public function testListingAliases($file, $hasAliases)
    {
        $command = (new PhinxApplication())->find('list:aliases');
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(
            [
                'command' => $command->getName(),
                '--configuration' => realpath(sprintf('%s/../../Config/_files/%s', __DIR__, $file)),
            ],
            ['decorated' => false]
        );
        $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);

        $display = $commandTester->getDisplay(false);

        if ($hasAliases) {
            $this->assertStringNotContainsString('No aliases defined in ', $display);
            $this->assertStringContainsString('Alias            Class                                             ', $display);
            $this->assertStringContainsString('================ ==================================================', $display);
            $this->assertStringContainsString('MakePermission   Vendor\Package\Migration\Creation\MakePermission  ', $display);
            $this->assertStringContainsString('RemovePermission Vendor\Package\Migration\Creation\RemovePermission', $display);
        } else {
            $this->assertStringContainsString('No aliases defined in ', $display);
        }
    }
}
