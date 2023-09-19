<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Command;

use Phinx\Console\Command\AbstractCommand;
use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

class ListTest extends TestCase
{
    public function testVersionInfo()
    {
        $application = new PhinxApplication();
        $application->setAutoExit(false); // Set autoExit to false when testing
        $application->setCatchExceptions(false);

        $appTester = new ApplicationTester($application);
        $exitCode = $appTester->run(['command' => 'list', '--format' => 'txt']);
        $this->assertSame(AbstractCommand::CODE_SUCCESS, $exitCode);
        $stream = $appTester->getOutput()->getStream();
        rewind($stream);

        $this->assertEquals(1, substr_count(stream_get_contents($stream), 'Phinx by CakePHP - https://phinx.org'));
    }
}
