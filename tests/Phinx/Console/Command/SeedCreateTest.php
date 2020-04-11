<?php

namespace Test\Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\SeedCreate;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
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

    public function setUp(): void
    {
        $this->config = new Config([
            'paths' => [
                'migrations' => sys_get_temp_dir(),
                'seeds' => sys_get_temp_dir(),
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'development',
                'development' => [
                    'adapter' => 'mysql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 3006,
                ],
            ],
        ]);

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        if (file_exists(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MyDuplicateSeeder.php')) {
            unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MyDuplicateSeeder.php');
        }

        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateSeeder'], ['decorated' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "MyDuplicateSeeder.php" already exists');

        $commandTester->execute(['command' => $command->getName(), 'name' => 'MyDuplicateSeeder'], ['decorated' => false]);
    }

    public function testExecuteWithInvalidClassName()
    {
        $application = new PhinxApplication();
        $application->add(new SeedCreate());

        /** @var SeedCreate $command */
        $command = $application->find('seed:create');

        // mock the manager class
        /** @var Manager|\PHPUnit\Framework\MockObject\MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class name "badseedname" is invalid. Please use CamelCase format');

        $commandTester->execute(['command' => $command->getName(), 'name' => 'badseedname'], ['decorated' => false]);
    }
}
