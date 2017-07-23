<?php
namespace Test\Phinx\Seed;

use Phinx\Config\Config;
use Phinx\Seed\AbstractSeed;
use Phinx\Seed\SeedFetcher;

class SeedFetcherTest extends \PHPUnit_Framework_TestCase
{
    private $output;

    public function setUp()
    {
        $this->output = $this->prophesize('Symfony\Component\Console\Output\OutputInterface')->reveal();
        parent::setUp();
    }

    /**
     * method fetch
     * when calledWithoutASeed
     * should returnAllSeedsInProperOrder
     */
    public function test_fetch_calledWithoutASeed_returnAllSeedsInProperOrder()
    {
        $sut = $this->getSut();

        $expected = array(
            'AGSeeder'                 => $this->getSeedInstance('\AGSeeder'),
            'APostSeeder'              => $this->getSeedInstance('\APostSeeder'),
            'AUserSeeder'              => $this->getSeedInstance('\AUserSeeder'),
            'GrandpaSeeder'            => $this->getSeedInstance('\GrandpaSeeder'),
            'DependsOnGrandpaSeeder'   => $this->getSeedInstance('\DependsOnGrandpaSeeder'),
            'ParentSeeder'             => $this->getSeedInstance('\ParentSeeder'),
            'DependsOnParentSeeder'    => $this->getSeedInstance('\DependsOnParentSeeder'),
            'DependsOnParentTooSeeder' => $this->getSeedInstance('\DependsOnParentTooSeeder'),
            'UncleSeeder'              => $this->getSeedInstance('\UncleSeeder'),
            'DependsOnUncleSeeder'     => $this->getSeedInstance('\DependsOnUncleSeeder'),
            'LowerLeaveSeeder'         => $this->getSeedInstance('\LowerLeaveSeeder'),
        );

        $actual = $sut->fetch();
        self::assertEquals(array_keys($expected), array_keys($actual));
    }

    /**
     * method fetch
     * when calledWithASeedWithParents
     * should returnOnlySeedAndParentsInProperOrder
     */
    public function test_fetch_calledWithASeedWithParents_returnOnlySeedAndParentsInProperOrder()
    {
        $sut = $this->getSut();

        $expected = array(
            'GrandpaSeeder'         => $this->getSeedInstance('\GrandpaSeeder'),
            'ParentSeeder'          => $this->getSeedInstance('\ParentSeeder'),
            'DependsOnParentSeeder' => $this->getSeedInstance('\DependsOnParentSeeder'),
        );
        $actual = $sut->fetch('DependsOnParentSeeder');
        self::assertEquals(array_keys($expected), array_keys($actual));
    }

    /**
     * method fetch
     * when calledWithASeedWithoutParents
     * should returnOnlyTheSeed
     */
    public function test_fetch_calledWithASeedWithoutParents_returnOnlyTheSeed()
    {
        $sut = $this->getSut();
        $expected = array(
            'APostSeeder' => $this->getSeedInstance('\APostSeeder'),
        );
        $actual = $sut->fetch('APostSeeder');
        self::assertEquals(array_keys($expected), array_keys($actual));
    }

    /**
     * @param string $class
     * @return AbstractSeed
     */
    private function getSeedInstance($class)
    {
        return (new $class())->setOutput($this->output);
    }

    private function getConfigArray()
    {
        return array(
            'paths' => array(
                'seeds' => [$this->getCorrectedPath(__DIR__ . '/_files/seeds')],
            )
        );
    }

    private function includeSeedFiles(Config $config)
    {
        $seed_files = array(
            'DependsOnGrandpaSeeder',
            'DependsOnParentSeeder',
            'DependsOnParentTooSeeder',
            'GrandpaSeeder',
            'AGSeeder',
            'ParentSeeder',
            'APostSeeder',
            'AUserSeeder',
            'DependsOnUncleSeeder',
            'LowerLeaveSeeder',
            'UncleSeeder'
        );
        foreach ($seed_files as $seed_file) {
            /** @noinspection PhpIncludeInspection */
            require_once $config->getSeedPaths()[0] . DIRECTORY_SEPARATOR . $seed_file . '.php';
        }
    }

    private function getCorrectedPath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @return SeedFetcher
     */
    private function getSut()
    {
        $config = new Config($this->getConfigArray());
        $sut = new SeedFetcher($config, $this->output);
        $this->includeSeedFiles($config);
        return $sut;
    }

}