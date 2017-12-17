<?php

namespace Test\Phinx\Console\Command;

use Phinx\Console\Command\Init;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Tester\CommandTester;

class InitTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $file = sys_get_temp_dir() . '/phinx.yml';
        if (is_file($file)) {
            unlink($file);
        }
    }

    protected function writeConfig($configName = '')
    {
        $application = new PhinxApplication('testing');
        $application->add(new Init());
        $command       = $application->find("init");
        $commandTester = new CommandTester($command);
        $fullPath      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $configName;

        $commandTester->execute([
            'command' => $command->getName(),
            'path'    => $fullPath,
        ], [
            'decorated' => false,
        ]);

        $this->assertRegExp(
            sprintf('|created %s|', $fullPath),
            $commandTester->getDisplay()
        );

        $this->assertFileExists(
            $fullPath,
            'Phinx configuration not existent'
        );
    }

    public function testDefaultConfigIsWritten()
    {
        $this->writeConfig();
    }

    public function testConfigIsWritten()
    {
        $this->writeConfig('phinx.yml');
    }

    public function testCustomNameConfigIsWritten()
    {
        $this->writeConfig(uniqid() . '.yml');
    }

    /**
     * @expectedException              \InvalidArgumentException
     * @expectedExceptionMessageRegExp /Config file already exists./
     */
    public function testThrowsExceptionWhenConfigFilePresent()
    {
        touch(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phinx.yml');
        $application = new PhinxApplication('testing');
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'path'    => sys_get_temp_dir(),
        ], [
            'decorated' => false,
        ]);
    }

    /**
     * @expectedException              \InvalidArgumentException
     * @expectedExceptionMessageRegExp /Invalid path for config file./
     */
    public function testThrowsExceptionWhenInvalidDir()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'path'    => '/this/dir/does/not/exists',
        ], [
            'decorated' => false,
        ]);
    }
}
