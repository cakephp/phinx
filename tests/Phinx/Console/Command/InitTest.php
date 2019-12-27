<?php

namespace Test\Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Console\Command\Init;
use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitTest extends TestCase
{
    public function setUp(): void
    {
        foreach (['.yml', '.json', '.php'] as $format) {
            $file = sys_get_temp_dir() . '/phinx' . $format;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    protected function writeConfig($configName = '')
    {
        $application = new PhinxApplication('testing');
        $application->add(new Init());
        $command = $application->find("init");
        $commandTester = new CommandTester($command);
        $fullPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $configName;

        $command = [
            'command' => $command->getName(),
            'path' => $fullPath,
        ];

        if ($configName !== '') {
            $command['--format'] = pathinfo($configName, PATHINFO_EXTENSION);
        }

        $commandTester->execute($command, ['decorated' => false]);

        $this->assertStringContainsString(
            "created $fullPath",
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
        $this->assertFileExists(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phinx.yml', 'Default format was not yaml');
    }

    public function formatDataProvider()
    {
        return [['.yml'], ['.json'], ['.php']];
    }

    /**
     * @dataProvider formatDataProvider
     *
     * @param string $format format to use for file
     */
    public function testConfigIsWritten($format)
    {
        $this->writeConfig('phinx' . $format);
    }

    /**
     * @dataProvider formatDataProvider
     *
     * @param string $format format to use for file
     */
    public function testCustomNameConfigIsWritten($format)
    {
        $this->writeConfig(uniqid() . $format);
    }

    public function testDefaults()
    {
        $current_dir = getcwd();
        chdir(sys_get_temp_dir());

        $application = new PhinxApplication('testing');
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);
        $this->assertRegExp(
            "/created (.*)[\/\\\\]phinx.yml\\n/",
            $commandTester->getDisplay(true)
        );

        $this->assertFileExists(
            'phinx.yml',
            'Phinx configuration not existent'
        );

        chdir($current_dir);
    }

    public function testThrowsExceptionWhenConfigFilePresent()
    {
        touch(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phinx.yml');
        $application = new PhinxApplication('testing');
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Config file ".*" already exists./');

        $commandTester->execute([
            'command' => $command->getName(),
            'path' => sys_get_temp_dir(),
        ], [
            'decorated' => false,
        ]);
    }

    public function testThrowsExceptionWhenInvalidDir()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid path ".*" for config file./');

        $commandTester->execute([
            'command' => $command->getName(),
            'path' => '/this/dir/does/not/exists',
        ], [
            'decorated' => false,
        ]);
    }

    public function testThrowsExceptionWhenInvalidFormat()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid format "invalid". Format must be either yml, json, or php.');

        $commandTester->execute([
            'command' => $command->getName(),
            'path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR,
            '--format' => 'invalid'
        ], [
            'decorated' => false
        ]);
    }
}
