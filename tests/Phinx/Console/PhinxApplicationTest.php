<?php

namespace Test\Phinx\Console;

use Phinx\Config\Config;
use Symfony\Component\Console\Tester\ApplicationTester;
use Phinx\Console\PhinxApplication;
use Phinx\Migration;
use Test\Phinx\BaseCommandTest;

class PhinxApplicationTest extends BaseCommandTest
{
    public function setup()
    {
        parent::setUp();

        $di = $this->di;

        $di['command.mockcreate'] = function() use($di) {
            $config = new Config(array('paths' => array('migrations' => __DIR__ . '/_files')));
            $createCommandMock = $this->getMockBuilder('Phinx\Console\Command\Create')
                ->setMockClassName('Mockcreate')
                ->setConstructorArgs(array($di))
                ->setMethods(array('getConfig'))
                ->getMock();
            $createCommandMock->expects($this->any())
                ->method('getConfig')
                ->will($this->returnValue($config));
            $createCommandMock->setName('mockcreate');

            return $createCommandMock;
        };

        $this->di = $di;
    }

    public function tearDown()
    {
        // Loop temp Dir to remove created PHP files
        $filesDir = __DIR__ . '/_files';

        if (is_dir($filesDir)) {
            if ($handle = opendir($filesDir)) {
                while (false !== ($entry = readdir($handle))) {
                    if (stripos($entry, '.php') !== false) {
                        $fullFilePath = $filesDir . DIRECTORY_SEPARATOR . $entry;
                        if (is_file($fullFilePath)) {
                            unlink($filesDir . DIRECTORY_SEPARATOR . $entry);
                        }
                    }
                }
            }
        }
    }

    /**
     * @dataProvider provider
     */
    public function testRun($command, $result)
    {
        $app = new \Phinx\Console\PhinxApplication($this->di, 'testing');
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

    public function testCreateSameName()
    {
        $app = new \Phinx\Console\PhinxApplication($this->di, 'testing');
        $app->add($this->di['command.mockcreate']);
        $app->setAutoExit(false); // Set autoExit to false when testing
        $app->setCatchExceptions(false);

        // To make sure the filename prefixed with different string
        $this->di['util'] = $this->_getUtilMock(123456);

        // Create migration with name 'Abc'
        $appTester = new ApplicationTester($app);
        $appTester->run(array('command' => 'mockcreate', 'name' => 'Abc'));

        // To make sure the filename prefixed with different string
        $this->di['util'] = $this->_getUtilMock(123457);

        // Create migration with name 'Abc', AGAIN! Expected exception raised
        $this->setExpectedException(
            'InvalidArgumentException',
            'The migration with same name already exist'
        );
        $appTester->run(array('command' => 'mockcreate', 'name' => 'Abc'));
        $stream = $appTester->getOutput()->getStream();
        rewind($stream);
    }

    /**
     * @param $returnValue
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function _getUtilMock($returnValue) {
        $utilMockClass = $this->getMockBuilder('Phinx\Migration\Util')
            ->setMethods(array('getCurrentTimestamp'))
            ->getMock();
        $utilMockClass->expects($this->any())
            ->method('getCurrentTimestamp')
            ->will($this->returnValue($returnValue));

        return $utilMockClass;
    }
}
