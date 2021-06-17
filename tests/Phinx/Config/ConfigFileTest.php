<?php

namespace Test\Phinx\Config;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Test\Phinx\Config\Command\VoidCommand;

class ConfigFileTest extends TestCase
{
    private $previousDir;

    private $baseDir;

    protected function setUp(): void
    {
        $this->previousDir = getcwd();
        $this->baseDir = realpath(__DIR__ . '/_rootDirectories');
    }

    protected function tearDown(): void
    {
        chdir($this->previousDir);
    }

    /**
     * Test workingContext
     *
     * @dataProvider workingProvider
     * @param $input
     * @param $dir
     * @param $expectedFile
     */
    public function testWorkingGetConfigFile($input, $dir, $expectedFile)
    {
        $foundPath = $this->runLocateFile($input, $dir);
        $expectedPath = $this->baseDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $expectedFile;

        $this->assertEquals($foundPath, $expectedPath);
    }

    /**
     * Test workingContext
     *
     * @dataProvider notWorkingProvider
     * @param $input
     * @param $dir
     */
    public function testNotWorkingGetConfigFile($input, $dir)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->runLocateFile($input, $dir);
    }

    /**
     * Do the locateFile Action
     *
     * @param $arg
     * @param $dir
     * @return string
     */
    protected function runLocateFile($arg, $dir)
    {
        chdir($this->baseDir . '/' . $dir);
        $definition = new InputDefinition([new InputOption('configuration')]);
        $input = new ArgvInput([], $definition);
        if ($arg) {
            $input->setOption('configuration', $arg);
        }
        $command = new VoidCommand('void');

        return $command->locateConfigFile($input);
    }

    /**
     * Working cases
     *
     * @return array
     */
    public function workingProvider()
    {
        return [
            //explicit yaml
            ['phinx.yml', 'OnlyYaml', 'phinx.yml'],
            //implicit with all choice
            [null, 'all', 'phinx.php'],
            //implicit with no php choice
            [null, 'noPhp', 'phinx.json'],
            //implicit with only yaml choice
            [null, 'OnlyYaml', 'phinx.yml'],
            //explicit Php
            ['phinx.php', 'all', 'phinx.php'],
            //explicit json
            ['phinx.json', 'all', 'phinx.json'],
        ];
    }

    /**
     * Not working cases
     *
     * @return array
     */
    public function notWorkingProvider()
    {
        return [
            //no valid file available
            [null, 'NoValidFile'],
            //called file not available
            ['phinx.yml', 'noYaml'],
            ['phinx.json', 'OnlyYaml'],
            ['phinx.php', 'OnlyYaml'],
        ];
    }
}
