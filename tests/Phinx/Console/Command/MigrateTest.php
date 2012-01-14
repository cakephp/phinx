<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester,
    Phinx\Console\Command\Migrate;

class MigrateTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Migrate());
        
        $command = $application->find('migrate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));
        
        $this->assertRegExp('/using migration path/', $commandTester->getDisplay());
    }
}