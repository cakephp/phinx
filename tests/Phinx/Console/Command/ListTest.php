<?php

namespace Test\Phinx\Console\Command;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Tester\ApplicationTester;

class ListTest extends \PHPUnit_Framework_TestCase
{
    public function testVersionInfo()
    {
        $application = new PhinxApplication();
        $application->setAutoExit(false); // Set autoExit to false when testing
        $application->setCatchExceptions(false);

        $appTester = new ApplicationTester($application);
        $appTester->run(['command' => 'list', '--format' => 'txt']);
        $stream = $appTester->getOutput()->getStream();
        rewind($stream);

        $this->assertEquals(1, substr_count(stream_get_contents($stream), 'Phinx by CakePHP - https://phinx.org'));
    }
}
