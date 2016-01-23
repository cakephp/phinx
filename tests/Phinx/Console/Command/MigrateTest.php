<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Migrate;

class MigrateTest extends \PHPUnit_Framework_TestCase
{
    protected $config = array();

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
    }

    public function testExecute()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Migrate());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('migrate');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
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
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Migrate());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('migrate');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
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
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Migrate());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('migrate');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
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
