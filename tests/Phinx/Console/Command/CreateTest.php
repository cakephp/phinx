<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Create;

class CreateTest extends \PHPUnit_Framework_TestCase
{
    protected $config = array();

    protected function setUp()
    {
        $this->config = new Config(array(
            'paths' => array(
                'migrations' => sys_get_temp_dir(),
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

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The migration class name "MyDuplicateMigration" already exists
     */
    public function testExecuteWithDuplicateMigrationNames()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Create());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('create');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $output));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyDuplicateMigration'));
        sleep(1.01); // need at least a second due to file naming scheme
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyDuplicateMigration'));
    }
}
