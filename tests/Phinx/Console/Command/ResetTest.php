<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Reset;
use Phinx\Migration\Manager;

class ResetTest extends \PHPUnit_Framework_TestCase
{
    protected $config = array();

    private $adapter = 'mysql';

    protected function setUp()
    {
        $prefix = 'TESTS_PHINX_DB_ADAPTER_' . strtoupper($this->adapter);
        if(constant($prefix.'_ENABLED')) {
            if($this->adapter=='sqlite') {
                $options = array('adapter' => $this->adapter, 'name' => constant($prefix.'_DATABASE'));
            } else {
                $options = array(
                    'adapter' => $this->adapter,
                    'host' => constant($prefix.'_HOST'),
                    'name' => constant($prefix.'_DATABASE'),
                    'user' => constant($prefix.'_USERNAME'),
                    'pass' => constant($prefix.'_PASSWORD'),
                    'port' => constant($prefix.'_PORT')
                );
            }
            if($this->adapter == 'pgsql') {
                $options['schema'] = constant($prefix.'_DATABASE_SCHEMA');
            }
        
            $this->config = new Config(array(
                'paths' => array(
                    'migrations' => __FILE__,
                    'schema' => __FILE__,
                ),
                'environments' => array(
                    'default_migration_table' => 'phinxlog',
                    'default_database' => 'development',
                    'production' => $options,
                    'development' => $options
                )
            ));
        }
        else 
            $this->markTestSkipped("Only running reset test on mysql");
    }

    public function testRefuseResettingProduction()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Reset());

        // setup dependencies
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $command = $application->find('reset');

        // mock the manager class
        $manager = new Manager($this->config, $output);

        $command->setConfig($this->config);
        $command->setManager($manager);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '--environment'=>'production' ), array('interactive'=>false));

        $display = $commandTester->getDisplay();
        $this->assertRegExp('/using environment production/', $display);
        $this->assertRegExp('/WARNING! It looks like you\'re trying to reset the database of a production environment./', $display);
    }

}
