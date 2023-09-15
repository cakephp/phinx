<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\Command\Init;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Test\Phinx\TestCase;

class InitTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['.yaml', '.yml', '.json', '.php'] as $format) {
            $file = sys_get_temp_dir() . '/phinx' . $format;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    protected function writeConfig($configName = '')
    {
        $application = new PhinxApplication();
        $application->add(new Init());
        $command = $application->find('init');
        $commandTester = new CommandTester($command);
        $fullPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $configName;

        $command = [
            'command' => $command->getName(),
            'path' => $fullPath,
        ];

        if ($configName !== '') {
            $command['--format'] = pathinfo($configName, PATHINFO_EXTENSION);
        }

        $exitCode = $commandTester->execute($command, ['decorated' => false]);
        $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);

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
        $this->assertFileExists(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phinx.php', 'Default format was not php');
    }

    public function formatDataProvider()
    {
        return [['.yml'], ['.json'], ['.php']];
    }

    /**
     * @dataProvider formatDataProvider
     * @param string $format format to use for file
     */
    public function testConfigIsWritten($format)
    {
        $this->writeConfig('phinx' . $format);
    }

    /**
     * @dataProvider formatDataProvider
     * @param string $format format to use for file
     */
    public function testCustomNameConfigIsWritten($format)
    {
        $this->writeConfig(uniqid() . $format);
    }

    public function testDefaults()
    {
        $current_dir = getcwd();

        try {
            chdir(sys_get_temp_dir());

            $application = new PhinxApplication();
            $application->add(new Init());

            $command = $application->find('init');

            $commandTester = new CommandTester($command);
            $exitCode = $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);
            $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);
            $this->assertMatchesRegularExpression(
                "/created (.*)[\/\\\\]phinx\.php\\n/",
                $commandTester->getDisplay(true)
            );

            $this->assertFileExists(
                'phinx.php',
                'Phinx configuration not existent'
            );
        } finally {
            chdir($current_dir);
        }
    }

    public function testYamlFormat()
    {
        $current_dir = getcwd();

        try {
            chdir(sys_get_temp_dir());

            $application = new PhinxApplication();
            $application->add(new Init());

            $command = $application->find('init');

            $commandTester = new CommandTester($command);
            $exitCode = $commandTester->execute(['command' => $command->getName(), '--format' => AbstractCommand::FORMAT_YML_ALIAS], ['decorated' => false]);
            $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);
            $this->assertMatchesRegularExpression(
                "/created (.*)[\/\\\\]phinx.yaml\\n/",
                $commandTester->getDisplay(true)
            );

            $this->assertFileExists(
                'phinx.yaml',
                'Phinx configuration not existent'
            );
        } finally {
            chdir($current_dir);
        }
    }

    public function testThrowsExceptionWhenConfigFilePresent()
    {
        touch(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phinx.php');
        $application = new PhinxApplication();
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Config file ".*" already exists./');

        $exitCode = $commandTester->execute([
            'command' => $command->getName(),
            'path' => sys_get_temp_dir(),
        ], [
            'decorated' => false,
        ]);
        $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testThrowsExceptionWhenInvalidDir()
    {
        $application = new PhinxApplication();
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid path ".*" for config file./');

        $exitCode = $commandTester->execute([
            'command' => $command->getName(),
            'path' => '/this/dir/does/not/exists',
        ], [
            'decorated' => false,
        ]);
        $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testThrowsExceptionWhenInvalidFormat()
    {
        $application = new PhinxApplication();
        $application->add(new Init());

        $command = $application->find('init');

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid format "invalid". Format must be either json, yaml, yml, php.');

        $exitCode = $commandTester->execute([
            'command' => $command->getName(),
            'path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR,
            '--format' => 'invalid',
        ], [
            'decorated' => false,
        ]);
        $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);
    }
}
