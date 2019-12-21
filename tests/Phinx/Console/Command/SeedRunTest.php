<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\Command\SeedRun;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class SeedRunTest extends TestCase
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

    protected function setUp()
    {
        $this->config = new Config([
            'paths' => [
                'migrations' => __FILE__,
                'seeds' => __FILE__,
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_database' => 'development',
                'development' => [
                    'adapter' => 'mysql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 3006,
                ]
            ]
        ]);

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('seed')->with($this->identicalTo('development'), $this->identicalTo(null));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('seed')
                    ->with('development');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--environment' => 'development'], ['decorated' => false]);
        $this->assertRegExp('/using environment development/', $commandTester->getDisplay());
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
    }

    public function testExecuteWithInvalidEnvironmentOption()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->never())
                    ->method('seed');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(['command' => $command->getName(), '--environment' => 'fakeenv'], ['decorated' => false]);

        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
        $this->assertStringEndsWith("The environment \"fakeenv\" does not exist", trim($commandTester->getDisplay()));
        $this->assertSame(AbstractCommand::CODE_ERROR, $exitCode);
    }

    public function testDatabaseNameSpecified()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('seed');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);
        $this->assertRegExp('/using database development/', $commandTester->getDisplay());
    }

    public function testExecuteMultipleSeeders()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->exactly(3))
                    ->method('seed')->withConsecutive(
                        [$this->identicalTo('development'), $this->identicalTo('One')],
                        [$this->identicalTo('development'), $this->identicalTo('Two')],
                        [$this->identicalTo('development'), $this->identicalTo('Three')]
                    );

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                '--seed' => ['One', 'Two', 'Three'],
            ],
            ['decorated' => false]
        );

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }
}
