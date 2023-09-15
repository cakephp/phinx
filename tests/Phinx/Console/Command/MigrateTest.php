<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Command;

use Phinx\Config\Config;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\Command\Migrate;
use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateTest extends TestCase
{
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
        $this->config = new Config([
            'paths' => [
                'migrations' => __FILE__,
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
        ]);

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('no environment specified', $output);
        $this->assertStringContainsString('ordering by creation time', $output);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testExecuteWithDsn()
    {
        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        $config = new Config([
            'paths' => [
                'migrations' => __FILE__,
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'development',
                'development' => [
                    'dsn' => 'mysql://fakehost:3006/development',
                ],
            ],
        ]);

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('no environment specified', $output);
        $this->assertStringContainsString('ordering by creation time', $output);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--environment' => 'development'], ['decorated' => false]);

        $this->assertStringContainsString('using environment development', $commandTester->getDisplay());
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testExecuteWithInvalidEnvironmentOption()
    {
        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->never())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--environment' => 'fakeenv'], ['decorated' => false]);

        $this->assertStringContainsString('using environment fakeenv', $commandTester->getDisplay());
        $this->assertStringEndsWith('The environment "fakeenv" does not exist', trim($commandTester->getDisplay()));
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testDatabaseNameSpecified()
    {
        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

        $this->assertStringContainsString('using database development', $commandTester->getDisplay());
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testFakeMigrate()
    {
        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
            ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--fake' => true], ['decorated' => false]);

        $this->assertStringContainsString('warning performing fake migrations', $commandTester->getDisplay());
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testMigrateExecutionOrder()
    {
        $this->config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;

        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('no environment specified', $output);
        $this->assertStringContainsString('ordering by execution time', $output);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testMigrateMemorySqlite()
    {
        $config = new Config([
            'paths' => [
                'migrations' => __FILE__,
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'development',
                'development' => [
                    'adapter' => 'sqlite',
                    'memory' => true,
                ],
            ],
        ]);

        $application = new PhinxApplication();
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--environment' => 'development'], ['decorated' => false]);

        $this->assertStringContainsString(implode(PHP_EOL, [
            'using environment development',
            'using adapter sqlite',
            'using database :memory:',
            'ordering by creation time',
        ]) . PHP_EOL, $commandTester->getDisplay());
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }
}
