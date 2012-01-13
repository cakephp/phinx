<?php

namespace Test\Phinx\Console;
 
use Symfony\Component\Console\Tester\ApplicationTester;

class PhinxApplicationTest
{
	/**
	 * @dataProvider provider
	 */
	public function testRun($command, $result)
	{
		$app = new \Phinx\Console\PhinxApplication();
		$app->setAutoExit(false); // Set autoExit to false when testing
		
		$appTester = new ApplicationTester($app);
		$appTester->run(array('command' => $command));
		$stream = $appTester->getOutput()->getStream();
		rewind($stream);
		
		$this->assertEquals(stream_get_contents($stream), $result);
	}
 
	public function provider()
	{
		return array(
	    	array('migrate', 'migrating')
	    );
	}
}