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
use Phinx\Console\Command\SeedCreate;

class SeedCreateTest extends \PHPUnit_Framework_TestCase
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
                'migrations' => sys_get_temp_dir(),
                'seeds' => sys_get_temp_dir(),
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

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The file "MyDuplicateSeeder.php" already exists
     */
    public function testExecute()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyDuplicateSeeder'), array('decorated' => false));
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyDuplicateSeeder'), array('decorated' => false));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The seed class name "badseedname" is invalid. Please use CamelCase format
     */
    public function testExecuteWithInvalidClassName()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'badseedname'), array('decorated' => false));
    }
}
