<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\SeedCreate;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

class SeedCreateTest extends TestCase
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
                'migrations' => sys_get_temp_dir(),
                'seeds' => sys_get_temp_dir(),
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
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateSeeder'], ['decorated' => false]);
        $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateSeeder'], ['decorated' => false]);
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
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), 'name' => 'badseedname'], ['decorated' => false]);
    }
}
