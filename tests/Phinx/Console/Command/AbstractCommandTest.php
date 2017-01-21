<?php

namespace Test\Phinx\Console\Command;

/**
 * @runTestsInSeparateProcesses
 */
class AbstractCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testFileFoundInDefaultPlace()
    {
        chdir(__DIR__ . '/Asset/default-test');
        $command = new Asset\FindConfigTestCommand();
        $foundFile = $command->callLocateConfigFile();
        $this->assertEquals(__DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['Asset', 'default-test', 'phinx.yml']), $foundFile);
    }

    public function testFindFileInCurrentWorkingDirectory()
    {
        putenv('PHINX_CONFIG_DIR=' . __DIR__ . '/Asset/env-test');
        $command = new Asset\FindConfigTestCommand();
        $foundFile = $command->callLocateConfigFile();
        $this->assertEquals(__DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['Asset', 'env-test', 'phinx.php']), $foundFile);
    }

    public function testFindFileInNestedConfigDirectory()
    {
        chdir(__DIR__ . '/Asset/app-test');
        $command = new Asset\FindConfigTestCommand();
        $foundFile = $command->callLocateConfigFile();
        $this->assertEquals(__DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['Asset', 'app-test', 'config', 'phinx.php']), $foundFile);
    }

}
