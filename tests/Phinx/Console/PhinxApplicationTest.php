<?php

namespace Test\Phinx\Console;

use Symfony\Component\Console\Tester\ApplicationTester;
use Phinx\Console\PhinxApplication;

class PhinxApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     */
    public function testRun($command, $result)
    {
        $app = new \Phinx\Console\PhinxApplication('testing');
        $app->setAutoExit(false); // Set autoExit to false when testing
        $app->setCatchExceptions(false);

        $appTester = new ApplicationTester($app);
        $appTester->run(array('command' => $command));
        $stream = $appTester->getOutput()->getStream();
        rewind($stream);

        $this->assertRegExp($result, stream_get_contents($stream));
    }

    public function provider()
    {
        return array(
            array('help', '/help \[--xml\] \[--format="..."\] \[--raw\] \[command_name\]/')
        );
    }
}
