<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Status;
use Test\Phinx\BaseCommandTest;

class StatusTest extends BaseCommandTest
{
    protected $config = array();

    protected function setUp()
    {
        parent::setUp();

        $this->config = new Config(array(
            'paths' => array(
                'migrations' => __FILE__,
            ),
            'environments' => array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'development',
                'development' => array(
                    'adapter' => 'pgsql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 5433,
                )
            )
        ));
    }

    public function testExecute()
    {
        $application = new \Phinx\Console\PhinxApplication($this->di, 'testing');
        $application->add(new Status($this->di));

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('status');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->once())
                    ->method('printStatus');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new \Phinx\Console\PhinxApplication($this->di, 'testing');
        $application->add(new Status($this->di));

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('status');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->once())
                    ->method('printStatus');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '--environment' => 'fakeenv'));
        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
    }

    public function testFormatSpecified()
    {
        $application = new \Phinx\Console\PhinxApplication($this->di, 'testing');
        $application->add(new Status($this->di));

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('status');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->once())
                    ->method('printStatus');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '--format' => 'json'));
        $this->assertRegExp('/using format json/', $commandTester->getDisplay());
    }
}
