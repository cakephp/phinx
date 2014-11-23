<?php

namespace Test\Phinx\Config;

use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;

class ConfigFileTest extends \PHPUnit_Framework_TestCase
{
    private $previousDir;

    private $baseDir;

    public function setUp()
    {
        $this->previousDir = getcwd();
        $this->baseDir = realpath(__DIR__ . '/_rootDirectories');
    }

    public function tearDown()
    {
        chdir($this->previousDir);
    }

    /**
     * Test workingContext
     *
     * @dataProvider workingProvider
     *
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
     *
     * @param $input
     * @param $dir
     * @expectedException \InvalidArgumentException
     */
    public function testNotWorkingGetConfigFile($input, $dir)
    {
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
        $definition = new InputDefinition(array(new InputOption('configuration')));
        $input = new ArgvInput(array(), $definition);
        if ($arg) {
            $input->setOption('configuration', $arg);
        }
        $command = new VoidCommand('void');
        return $command->locateConfigFile($input);
    }

    /**
     * Working cases
     *
     *
     * @return array
     */
    public function workingProvider()
    {
        return array(
            //explicit yaml
            array('phinx.yml', 'OnlyYaml', 'phinx.yml'),
            //implicit with all choice
            array(null, 'all', 'phinx.php'),
            //implicit with no php choice
            array(null, 'noPhp', 'phinx.json'),
            //implicit with only yaml choice
            array(null, 'OnlyYaml', 'phinx.yml'),
            //explicit Php
            array('phinx.php', 'all', 'phinx.php'),
            //explicit json
            array('phinx.json', 'all', 'phinx.json'),
        );
    }

    /**
     * Not working cases
     *
     * @return array
     */
    public function notWorkingProvider()
    {
        return array(
            //no valid file available
            array(null, 'NoValidFile'),
            //called file not available
            array('phinx.yml', 'noYaml'),
            array('phinx.json', 'OnlyYaml'),
            array('phinx.php', 'OnlyYaml'),
        );
    }
}

/**
 * Class VoidCommand : used to expose locateConfigFile To testing
 *
 * @package Test\Phinx\Config
 */
class VoidCommand extends AbstractCommand
{
    public function locateConfigFile(InputInterface $input)
    {
        return parent::locateConfigFile($input);
    }
}
