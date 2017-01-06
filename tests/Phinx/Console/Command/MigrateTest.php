<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\Migrate;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigInterface|array
     */
    protected $config = array();

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
        $this->config = new Config(array(
            'paths' => array(
                'migrations' => __FILE__,
            ),
            'environments' => array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'development',
                'development' => array(
                    'adapter' => 'mysql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 3006,
                )
            )
        ));

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', [], [$this->config, $this->input, $this->output]);
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
        $this->assertSame(0, $exitCode);
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', [], [$this->config, $this->input, $this->output]);
        $managerStub->expects($this->any())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(array('command' => $command->getName(), '--environment' => 'fakeenv'), array('decorated' => false));

        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
        $this->assertSame(1, $exitCode);
    }

    public function testDatabaseNameSpecified()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Migrate());

        /** @var Migrate $command */
        $command = $application->find('migrate');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', [], [$this->config, $this->input, $this->output]);
        $managerStub->expects($this->once())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $this->assertRegExp('/using database development/', $commandTester->getDisplay());
        $this->assertSame(0, $exitCode);
    }
}
