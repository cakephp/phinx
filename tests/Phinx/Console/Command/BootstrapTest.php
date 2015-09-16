<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Status;

class BootstrapTest extends \PHPUnit_Framework_TestCase
{
    protected $config = array();

    protected function setUp()
    {
        $this->config = new Config(
            array(
                'paths'        => array(
                    'migrations' => __FILE__,
                ),
                'environments' => array(
                    'default_migration_table' => 'phinxlog',
                    'default_database'        => 'development',
                    'development'             => array(
                        'adapter' => 'pgsql',
                        'host'    => 'fakehost',
                        'name'    => 'development',
                        'user'    => '',
                        'pass'    => '',
                        'port'    => 5433,
                    )
                )
            )
        );
    }

    public function testExecute()
    {
        $application = new \Phinx\Console\PhinxApplication();
        $application->add(new Status());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('bootstrap');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));
        $managerStub->expects($this->once())
            ->method('executeBootstrap')->with('development', false, null);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }




}
