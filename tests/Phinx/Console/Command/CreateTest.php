<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\Create;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class CreateTest
 * @package Test\Phinx\Console\Command
 * @group create
 */
class CreateTest extends \PHPUnit_Framework_TestCase
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
        @mkdir(sys_get_temp_dir().DIRECTORY_SEPARATOR.'migrations', 0777, true);
        $this->config = new Config(
            array(
                'paths' => array(
                    'migrations' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'migrations',
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
                    ),
                ),
            )
        );

        foreach (glob($this->config->getMigrationPath().'/*.*') as $migration) {
            unlink($migration);
        }

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The migration class name "MyDuplicateMigration" already exists
     */
    public function testExecuteWithDuplicateMigrationNames()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyDuplicateMigration'));
        sleep(1.01); // need at least a second due to file naming scheme
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyDuplicateMigration'));
    }


    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot use --template and --class at the same time
     */
    public function testSupplyingBothClassAndTemplateAtCommandLineThrowsException()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyFailingMigration', '--template' => 'MyTemplate', '--class' => 'MyTemplateClass'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Cannot define template:class and template:file at the same time
     */
    public function testSupplyingBothClassAndTemplateInConfigThrowsException()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        $this->config['templates'] = array(
            'file' => 'MyTemplate',
            'class' => 'MyTemplateClass',
        );

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'name' => 'MyFailingMigration'));
    }

    public function provideFailingTemplateGenerator()
    {
        return array(
            array(
                array(
                    'templates' => array(
                        'class' => '\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface',
                    ),
                ),
                array(),
                'The class "\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface" does not implement the required interface "Phinx\Migration\CreationInterface"',
            ),
            array(
                array(),
                array('--class' => '\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface'),
                'The class "\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface" does not implement the required interface "Phinx\Migration\CreationInterface"',
            ),
            array(
                array(
                    'aliases' => array(
                        'PoorInterface' => '\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface',
                    ),
                ),
                array(
                    '--class' => 'PoorInterface',
                ),
                'The class "\Test\Phinx\Console\Command\TemplateGenerators\DoesNotImplementRequiredInterface" via the alias "PoorInterface" does not implement the required interface "Phinx\Migration\CreationInterface"',
            ),
        );
    }

    /**
     * @param array $config
     * @param array $commandLine
     * @param string $exceptionMessage
     * @dataProvider provideFailingTemplateGenerator
     */
    public function testTemplateGeneratorsWithoutCorrectInterfaceThrowsException(array $config, array $commandLine, $exceptionMessage)
    {
        $application = new PhinxApplication('testing');
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $this->setExpectedException('\InvalidArgumentException', $exceptionMessage);
        $commandLine = array_merge(array('command' => $command->getName(), 'name' => 'MyFailingMigration'), $commandLine);
        $commandTester->execute($commandLine);
    }

    public function provideNullTemplateGenerator()
    {
        return array(
            array(
                array(
                    'templates' => array(
                        'class' => '\Test\Phinx\Console\Command\TemplateGenerators\NullGenerator',
                    ),
                ),
                array('name' => 'Null1'),
            ),
            array(
                array(),
                array(
                    'name' => 'Null2',
                    '--class' => '\Test\Phinx\Console\Command\TemplateGenerators\NullGenerator'
                ),
            ),
            array(
                array(
                    'aliases' => array(
                        'NullGen' => '\Test\Phinx\Console\Command\TemplateGenerators\NullGenerator',
                    ),
                ),
                array(
                    'name' => 'Null3',
                    '--class' => 'NullGen',
                ),
            ),
        );
    }

    /**
     * @param array $config
     * @param array $commandLine
     * @dataProvider provideNullTemplateGenerator
     */
    public function testNullTemplateGeneratorsDoNotFail(array $config, array $commandLine)
    {
        $application = new PhinxApplication('testing');
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(array('command' => $command->getName()), $commandLine);
        $commandTester->execute($commandLine);
    }

    public function provideSimpleTemplateGenerator()
    {
        return array(
            array(
                array(
                    'templates' => array(
                        'class' => '\Test\Phinx\Console\Command\TemplateGenerators\SimpleGenerator',
                    ),
                ),
                array('name' => 'Simple1'),
            ),
            array(
                array(),
                array(
                    'name' => 'Simple2',
                    '--class' => '\Test\Phinx\Console\Command\TemplateGenerators\SimpleGenerator'
                ),
            ),
            array(
                array(
                    'aliases' => array(
                        'SimpleGen' => '\Test\Phinx\Console\Command\TemplateGenerators\SimpleGenerator',
                    ),
                ),
                array(
                    'name' => 'Simple3',
                    '--class' => 'SimpleGen',
                ),
            ),
        );
    }

    /**
     * @param array $config
     * @param array $commandLine
     * @dataProvider provideSimpleTemplateGenerator
     */
    public function testSimpleTemplateGeneratorsIsCorrectlyPopulated(array $config, array $commandLine)
    {
        $application = new PhinxApplication('testing');
        $application->add(new Create());

        /** @var Create $command $command */
        $command = $application->find('create');

        /** @var Manager $managerStub mock the manager class */
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));

        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);

        $commandLine = array_merge(array('command' => $command->getName()), $commandLine);
        $commandTester->execute($commandLine, array('decorated' => false));

        // Get output.
        preg_match('`created (?P<MigrationFilename>.+(?P<Version>\d{14}).*?)\s`', $commandTester->getDisplay(), $match);

        // Was migration created?
        $this->assertFileExists($match['MigrationFilename'], 'Failed to create migration file from template generator');

        // Get migration.
        $actualMigration = file_get_contents($match['MigrationFilename']);

        // Does the migration match our expectation?
        $expectedMigration = "useClassName Phinx\\Migration\\AbstractMigration / className {$commandLine['name']} / version {$match['Version']} / baseClassName AbstractMigration";
        $this->assertSame($expectedMigration, $actualMigration, 'Failed to create migration file from template generator correctly.');
    }
}
