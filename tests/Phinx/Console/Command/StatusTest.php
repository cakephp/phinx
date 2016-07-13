<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\ConfigInterface;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Status;

class StatusTest extends \PHPUnit_Framework_TestCase
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
                    'adapter' => 'pgsql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 5433,
                )
            )
        ));

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Status());

        /** @var Status $command */
        $command = $application->find('status');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));
        $managerStub->expects($this->once())
                    ->method('printStatus')
                    ->will($this->returnValue(0));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $return = $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $this->assertEquals(0, $return);
        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Status());

        /** @var Status $command */
        $command = $application->find('status');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));
        $managerStub->expects($this->once())
                    ->method('printStatus')
                    ->will($this->returnValue(0));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $return = $commandTester->execute(array('command' => $command->getName(), '--environment' => 'fakeenv'), array('decorated' => false));
        $this->assertEquals(0, $return);
        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
    }

    public function testFormatSpecified()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Status());

        /** @var Status $command */
        $command = $application->find('status');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));
        $managerStub->expects($this->once())
                    ->method('printStatus')
                    ->will($this->returnValue(0));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $return = $commandTester->execute(array('command' => $command->getName(), '--format' => 'json'), array('decorated' => false));
        $this->assertEquals(0, $return);
        $this->assertRegExp('/using format json/', $commandTester->getDisplay());
    }
}
