<?php

namespace Test\Phinx\Console;

use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

class PhinxApplicationTest extends TestCase
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
        $appTester->run(['command' => $command]);
        $stream = $appTester->getOutput()->getStream();
        rewind($stream);

        $this->assertRegExp($result, stream_get_contents($stream));
    }

    public function provider()
    {
        return [
            ['help', '/help \[options\] \[--\] \[<command_name>\]/']
        ];
    }
}
