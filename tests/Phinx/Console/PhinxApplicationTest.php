<?php
declare(strict_types=1);

namespace Test\Phinx\Console;

use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

class PhinxApplicationTest extends TestCase
{
    /**
     * @dataProvider provider
     * @param \Phinx\Console\Command\AbstractCommand $command
     * @param $result
     */
    public function testRun($command, $result)
    {
        $app = new PhinxApplication();
        $app->setAutoExit(false); // Set autoExit to false when testing
        $app->setCatchExceptions(false);

        $appTester = new ApplicationTester($app);
        $appTester->run(['command' => $command]);
        $stream = $appTester->getOutput()->getStream();
        rewind($stream);

        $contents = stream_get_contents($stream);

        $this->assertMatchesRegularExpression('/^Phinx by CakePHP - https:\/\/phinx.org\. .+\n/', $contents);
        $this->assertStringContainsString($result, $contents);
    }

    public function provider()
    {
        return [
            ['help', 'help [options] [--] [<command_name>]'],
        ];
    }
}
