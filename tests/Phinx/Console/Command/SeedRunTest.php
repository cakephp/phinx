<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\SeedRun;

class SeedRunTest extends \PHPUnit_Framework_TestCase
{
    protected $config = array();

    protected function setUp()
    {
        $this->config = new Config(array(
            'paths' => array(
                'migrations' => __FILE__,
                'seeds' => __FILE__,
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
        $application->add(new SeedRun());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('seed:run');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->once())
                    ->method('seed')->with($this->identicalTo('development'), $this->identicalTo(null));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new SeedRun());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('seed:run');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->any())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '--environment' => 'fakeenv'), array('decorated' => false));
        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
    }

    public function testDatabaseNameSpecified()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new SeedRun());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('seed:run');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->once())
                    ->method('seed');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));
        $this->assertRegExp('/using database development/', $commandTester->getDisplay());
    }

    public function testExecuteMultipleSeeders()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new SeedRun());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('seed:run');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->exactly(3))
                    ->method('seed')->withConsecutive(
                        array($this->identicalTo('development'), $this->identicalTo('One')),
                        array($this->identicalTo('development'), $this->identicalTo('Two')),
                        array($this->identicalTo('development'), $this->identicalTo('Three'))
                    );

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command' => $command->getName(),
                '--seed' => ['One', 'Two', 'Three'],
            ),
            array('decorated' => false)
        );

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }
}
