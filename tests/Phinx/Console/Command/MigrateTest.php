<?php

namespace Test\Phinx\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Phinx\Console\Command\Migrate;

class MigrateTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider provider
	 */
	 public function testExecute($options)
	 {
		 $commandTester = new CommandTester(new Migrate());
		 $commandTester->execute();
		 $this->assertEquals($commandTester->getDisplay(), 'migrating');
	 }
	 
	 /**
	  * Data Provider for execute method.
	  */
	 public function provider()
	 {
		 return array(
			 array()
		 );
	 }
}