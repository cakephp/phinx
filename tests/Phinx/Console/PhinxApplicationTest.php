<?php

namespace Test\Phinx\Console;

use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Tester\ApplicationTester;
use Phinx\Console\PhinxApplication;

class PhinxApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     *
     * @param AbstractCommand $command
     * @param $result
     */
    public function testRun($command, $result)
    {
        $app = new PhinxApplication('testing');
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
            array('help', '/help \[options\] \[--\] \[<command_name>\]/')
        );
    }
}
