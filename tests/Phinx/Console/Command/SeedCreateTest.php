<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\Command\SeedCreate;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Test\Phinx\TestUtils;

class SeedCreateTest extends TestCase
{
    /**
     * @var array
     */
    protected $configValues = [];

    /**
     * @var ConfigInterface|array
     */
    protected $config = [];

    /**
     * @var InputInterface $input
     */
    protected $input;

    /**
     * @var OutputInterface $output
     */
    protected $output;

    protected function setUp(): void
    {
        TestUtils::recursiveRmdir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seeds');
        mkdir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seeds', 0777, true);
        $this->configValues = [
            'paths' => [
                'migrations' => sys_get_temp_dir(),
                'seeds' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seeds',
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'development',
                'development' => [
                    'adapter' => 'mysql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 3006,
                ],
            ],
        ];
        $this->config = new Config($this->configValues);

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        if (file_exists(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MyDuplicateSeeder.php')) {
            unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MyDuplicateSeeder.php');
        }

        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateSeeder'], ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "MyDuplicateSeeder.php" already exists');

        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateSeeder'], ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testExecuteWithInvalidClassName()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class name "badseedname" is invalid. Please use CamelCase format');

        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'badseedname'], ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testAlternativeTemplateFromConsole()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = ['command' => $command->getName(), 'name' => 'AltTemplate', '--template' => __DIR__ . '/Templates/SimpleSeeder.template.php.dist'];
        $exitCode = $commandTester->execute($commandLine, ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        // Get output.
        preg_match('`created (?P<SeedFilename>.*?)\s`', $commandTester->getDisplay(), $match);

        // Was migration created?
        $this->assertFileExists($match['SeedFilename'], 'Failed to create seed file from template generator');

        // Does the migration match our expectation?
        $expectedMigration = "useClassName Phinx\\Seed\\AbstractSeed / className {$commandLine['name']} / baseClassName AbstractSeed\n";
        $this->assertStringEqualsFile($match['SeedFilename'], $expectedMigration, 'Failed to create seed file from template generator correctly.');
    }

    public function testAlternativeTemplateFromConsoleDoesntExist()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $template = __DIR__ . '/Templates/ThisDoesntExist.template.php.dist';
        $commandLine = ['command' => $command->getName(), 'name' => 'AltTemplateDoesntExist', '--template' => $template];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The template file "' . $template . '" does not exist');

        $exitCode = $commandTester->execute($commandLine, ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testAlternativeTemplateFromConfigOnly()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        $this->configValues['templates']['seedFile'] = __DIR__ . '/Templates/SimpleSeeder.templateFromConfig.php.dist';
        $this->config = new Config($this->configValues);
        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = ['command' => $command->getName(), 'name' => 'AltTemplate'];
        $exitCode = $commandTester->execute($commandLine, ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        // Get output.
        preg_match('`created (?P<SeedFilename>.*?)\s`', $commandTester->getDisplay(), $match);

        // Was migration created?
        $this->assertFileExists($match['SeedFilename'], 'Failed to create seed file from template generator');

        // Does the migration match our expectation?
        $expectedMigration = "useClassName Phinx\\Seed\\AbstractSeed / className {$commandLine['name']} / baseClassName AbstractSeed\n"
            . "This file should be specified in config only, not in console runs\n";
        $this->assertStringEqualsFile($match['SeedFilename'], $expectedMigration, 'Failed to create seed file from template generator correctly.');
    }

    public function testAlternativeTemplateFromConfigOnlyDoesntExist()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        $template = __DIR__ . '/Templates/SimpleSeeder.templateFromConfigNotExist.php.dist';
        $this->configValues['templates']['seedFile'] = $template;
        $this->config = new Config($this->configValues);
        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = ['command' => $command->getName(), 'name' => 'AltTemplate'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The template file `' . $template . '` from config does not exist');

        $exitCode = $commandTester->execute($commandLine, ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testAlternativeTemplateFromConfigAndConsole()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        $this->configValues['templates']['seedFile'] = __DIR__ . '/Templates/SimpleSeeder.templateFromConfig.php.dist';
        $this->config = new Config($this->configValues);
        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder(Manager::class)
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = ['command' => $command->getName(), 'name' => 'AltTemplate', '--template' => __DIR__ . '/Templates/SimpleSeeder.template.php.dist'];
        $exitCode = $commandTester->execute($commandLine, ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        // Get output.
        preg_match('`created (?P<SeedFilename>.*?)\s`', $commandTester->getDisplay(), $match);

        // Was migration created?
        $this->assertFileExists($match['SeedFilename'], 'Failed to create seed file from template generator');

        // Does the migration match our expectation?
        // In this case we expect content of template from Console argument to have higher priority
        $expectedMigration = "useClassName Phinx\\Seed\\AbstractSeed / className {$commandLine['name']} / baseClassName AbstractSeed\n";
        $this->assertStringEqualsFile($match['SeedFilename'], $expectedMigration, 'Failed to create seed file from template generator correctly.');
    }
}
