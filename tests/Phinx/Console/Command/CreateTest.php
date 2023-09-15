<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Command;

use Exception;
use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\Command\Create;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Test\Phinx\TestCase;
use Test\Phinx\TestUtils;

/**
 * Class CreateTest
 *
 * @package Test\Phinx\Console\Command
 * @group create
 */
class CreateTest extends TestCase
{
    /**
     * @var \Phinx\Config\ConfigInterface
     */
    protected $config;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface $input
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Input\OutputInterface $output
     */
    protected $output;

    protected function setUp(): void
    {
        TestUtils::recursiveRmdir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations');
        @mkdir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations', 0777, true);
        $this->config = new Config(
            [
                'paths' => [
                    'migrations' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations',
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
            ]
        );

        foreach ($this->config->getMigrationPaths() as $path) {
            foreach (glob($path . '/*.*') as $migration) {
                unlink($migration);
            }
        }

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testCreateMigrationDefault(): void
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName()]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        $files = array_diff(scandir($this->config->getMigrationPaths()[0]), ['.', '..']);
        $this->assertCount(1, $files);
        $fileName = current($files);
        $this->assertMatchesRegularExpression('/^[0-9]{14}.php/', $fileName);
        $date = substr($fileName, 0, 14);
        $this->assertFileExists($this->config->getMigrationPaths()[0]);
        $prefix = "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\nfinal class V{$date} extends AbstractMigration\n";
        $migrationContents = file_get_contents($this->config->getMigrationPaths()[0] . DIRECTORY_SEPARATOR . $fileName);
        $this->assertStringStartsWith($prefix, $migrationContents);
        $this->assertStringContainsString('public function change()', $migrationContents);
        $this->assertStringNotContainsString('public function up()', $migrationContents);
        $this->assertStringNotContainsString('public function down()', $migrationContents);
    }

    public function testCreateMigrationWithName(): void
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyMigration']);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        $files = array_diff(scandir($this->config->getMigrationPaths()[0]), ['.', '..']);
        $this->assertCount(1, $files);
        $fileName = current($files);
        $this->assertMatchesRegularExpression('/^[0-9]{14}_my_migration.php/', $fileName);
        $this->assertFileExists($this->config->getMigrationPaths()[0]);
        $prefix = "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\nfinal class MyMigration extends AbstractMigration\n";
        $this->assertStringStartsWith($prefix, file_get_contents($this->config->getMigrationPaths()[0] . DIRECTORY_SEPARATOR . $fileName));
    }

    public function testExecuteWithDuplicateMigrationNames()
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateMigration']);
        time_nanosleep(1, 100000); // need at least a second due to file naming scheme

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The migration class name "MyDuplicateMigration" already exists');

        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateMigration']);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testExecuteWithDuplicateMigrationNamesWithNamespace()
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $config = clone $this->config;
        $config['paths'] = [
            'migrations' => [
                'Foo\Bar' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations',
            ],
        ];
        $command->setConfig($config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateMigration']);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
        time_nanosleep(1, 100000); // need at least a second due to file naming scheme

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The migration class name "Foo\Bar\MyDuplicateMigration" already exists');

        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateMigration']);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testSupplyingBothClassAndTemplateAtCommandLineThrowsException()
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot use --template and --class at the same time');

        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyFailingMigration', '--template' => 'MyTemplate', '--class' => 'MyTemplateClass']);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testSupplyingBothClassAndTemplateInConfigThrowsException()
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $this->config['templates'] = [
            'file' => 'MyTemplate',
            'class' => 'MyTemplateClass',
        ];

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot define template:class and template:file at the same time');

        $exitCode = $commandTester->execute(['command' => $command->getName(), 'name' => 'MyFailingMigration']);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function provideFailingTemplateGenerator()
    {
        return [
            [
                [
                    'templates' => [
                        'class' => '\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface',
                    ],
                ],
                [],
                'The class "\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface" does not implement the required interface "Phinx\Migration\CreationInterface"',
            ],
            [
                [],
                ['--class' => '\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface'],
                'The class "\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface" does not implement the required interface "Phinx\Migration\CreationInterface"',
            ],
            [
                [
                    'aliases' => [
                        'PoorInterface' => '\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface',
                    ],
                ],
                [
                    '--class' => 'PoorInterface',
                ],
                'The class "\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface" via the alias "PoorInterface" does not implement the required interface "Phinx\Migration\CreationInterface"',
            ],
        ];
    }

    /**
     * @param array $config
     * @param array $commandLine
     * @param string $exceptionMessage
     * @dataProvider provideFailingTemplateGenerator
     */
    public function testTemplateGeneratorsWithoutCorrectInterfaceThrowsException(array $config, array $commandLine, $exceptionMessage)
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(['command' => $command->getName(), 'name' => 'MyFailingMigration'], $commandLine);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $exitCode = $commandTester->execute($commandLine);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function provideNullTemplateGenerator()
    {
        return [
            [
                [
                    'templates' => [
                        'class' => '\Test\Phinx\Console\Command\TemplateGenerators\NullGenerator',
                    ],
                ],
                ['name' => 'Null1'],
            ],
            [
                [],
                [
                    'name' => 'Null2',
                    '--class' => '\Test\Phinx\Console\Command\TemplateGenerators\NullGenerator',
                ],
            ],
            [
                [
                    'aliases' => [
                        'NullGen' => '\Test\Phinx\Console\Command\TemplateGenerators\NullGenerator',
                    ],
                ],
                [
                    'name' => 'Null3',
                    '--class' => 'NullGen',
                ],
            ],
        ];
    }

    /**
     * @param array $config
     * @param array $commandLine
     * @dataProvider provideNullTemplateGenerator
     */
    public function testNullTemplateGeneratorsDoNotFail(array $config, array $commandLine)
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(['command' => $command->getName()], $commandLine);
        $exitCode = $commandTester->execute($commandLine);
        $this->assertEquals(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function provideSimpleTemplateGenerator()
    {
        return [
            [
                [
                    'templates' => [
                        'class' => '\Test\Phinx\Console\Command\TemplateGenerators\SimpleGenerator',
                    ],
                ],
                ['name' => 'Simple1'],
            ],
            [
                [],
                [
                    'name' => 'Simple2',
                    '--class' => '\Test\Phinx\Console\Command\TemplateGenerators\SimpleGenerator',
                ],
            ],
            [
                [
                    'aliases' => [
                        'SimpleGen' => '\Test\Phinx\Console\Command\TemplateGenerators\SimpleGenerator',
                    ],
                ],
                [
                    'name' => 'Simple3',
                    '--class' => 'SimpleGen',
                ],
            ],
        ];
    }

    /**
     * @param array $config
     * @param array $commandLine
     * @dataProvider provideSimpleTemplateGenerator
     */
    public function testSimpleTemplateGeneratorsIsCorrectlyPopulated(array $config, array $commandLine)
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(['command' => $command->getName()], $commandLine);
        $exitCode = $commandTester->execute($commandLine, ['decorated' => false]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        // Get output.
        preg_match('`created (?P<MigrationFilename>.+(?P<Version>\d{14}).*?)\s`', $commandTester->getDisplay(), $match);

        // Was migration created?
        $this->assertFileExists($match['MigrationFilename'], 'Failed to create migration file from template generator');

        // Does the migration match our expectation?
        $expectedMigration = "useClassName Phinx\\Migration\\AbstractMigration / className {$commandLine['name']} / version {$match['Version']} / baseClassName AbstractMigration";
        $this->assertStringEqualsFile($match['MigrationFilename'], $expectedMigration, 'Failed to create migration file from template generator correctly.');
    }

    public function testCreateMigrationWithChangeStyleInConfig(): void
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        $this->config['templates'] = ['style' => 'change'];

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName()]);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        $files = array_diff(scandir($this->config->getMigrationPaths()[0]), ['.', '..']);
        $this->assertCount(1, $files);
        $fileName = current($files);
        $this->assertMatchesRegularExpression('/^[0-9]{14}.php/', $fileName);
        $date = substr($fileName, 0, 14);
        $this->assertFileExists($this->config->getMigrationPaths()[0]);
        $prefix = "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\nfinal class V{$date} extends AbstractMigration\n";
        $migrationContents = file_get_contents($this->config->getMigrationPaths()[0] . DIRECTORY_SEPARATOR . $fileName);
        $this->assertStringStartsWith($prefix, $migrationContents);
        $this->assertStringContainsString('public function change()', $migrationContents);
        $this->assertStringNotContainsString('public function up()', $migrationContents);
        $this->assertStringNotContainsString('public function down()', $migrationContents);
    }

    public function testCreateMigrationWithChangeStyleAsFlag(): void
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--style' => 'change']);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        $files = array_diff(scandir($this->config->getMigrationPaths()[0]), ['.', '..']);
        $this->assertCount(1, $files);
        $fileName = current($files);
        $this->assertMatchesRegularExpression('/^[0-9]{14}.php/', $fileName);
        $date = substr($fileName, 0, 14);
        $this->assertFileExists($this->config->getMigrationPaths()[0]);
        $prefix = "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\nfinal class V{$date} extends AbstractMigration\n";
        $migrationContents = file_get_contents($this->config->getMigrationPaths()[0] . DIRECTORY_SEPARATOR . $fileName);
        $this->assertStringStartsWith($prefix, $migrationContents);
        $this->assertStringContainsString('public function change()', $migrationContents);
        $this->assertStringNotContainsString('public function up()', $migrationContents);
        $this->assertStringNotContainsString('public function down()', $migrationContents);
    }

    public function testCreateMigrationWithUpDownStyleAsFlag(): void
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--style' => 'up_down']);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);

        $files = array_diff(scandir($this->config->getMigrationPaths()[0]), ['.', '..']);
        $this->assertCount(1, $files);
        $fileName = current($files);
        $this->assertMatchesRegularExpression('/^[0-9]{14}.php/', $fileName);
        $date = substr($fileName, 0, 14);
        $this->assertFileExists($this->config->getMigrationPaths()[0]);
        $prefix = "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\nfinal class V{$date} extends AbstractMigration\n";
        $migrationContents = file_get_contents($this->config->getMigrationPaths()[0] . DIRECTORY_SEPARATOR . $fileName);
        $this->assertStringStartsWith($prefix, $migrationContents);
        $this->assertStringContainsString('public function up()', $migrationContents);
        $this->assertStringContainsString('public function down()', $migrationContents);
        $this->assertStringNotContainsString('public function change()', $migrationContents);
    }

    public function testCreateMigrationWithInvalidStyleFlagThrows(): void
    {
        $application = new PhinxApplication();
        $application->add(new Create());

        /** @var Create $command */
        $command = $application->find('create');

        /** @var \Phinx\Migration\Manager $managerStub mock the manager class */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--style should be one of change or up_down');

        $exitCode = $commandTester->execute(['command' => $command->getName(), '--style' => 'foo']);
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }
}
