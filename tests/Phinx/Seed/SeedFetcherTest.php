<?php
namespace Test\Phinx\Seed;

use Phinx\Config\Config;
use Phinx\Seed\SeedFetcher;

class SeedFetcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * method fetch
     * when calledWithoutASeed
     * should returnAllSeedsInProperOrder
     */
    public function test_fetch_calledWithoutASeed_returnAllSeedsInProperOrder()
    {
        $sut = $this->getSut();

        $expected = array(
            'GrandpaSeeder'            => new \GrandpaSeeder(),
            'DependsOnGrandpaSeeder'   => new \DependsOnGrandpaSeeder(),
            'ParentSeeder'             => new \ParentSeeder(),
            'DependsOnParentSeeder'    => new \DependsOnParentSeeder(),
            'DependsOnParentTooSeeder' => new \DependsOnParentTooSeeder(),
            'GSeeder'                  => new \GSeeder(),
            'PostSeeder'               => new \PostSeeder(),
            'UserSeeder'               => new \UserSeeder(),
        );

        $actual = $sut->fetch();
        self::assertEquals($expected, $actual);
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
            'GrandpaSeeder'         => new \GrandpaSeeder(),
            'ParentSeeder'          => new \ParentSeeder(),
            'DependsOnParentSeeder' => new \DependsOnParentSeeder(),
        );
        $actual = $sut->fetch('DependsOnParentSeeder');
        self::assertEquals($expected, $actual);
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
            'PostSeeder' => new \PostSeeder(),
        );
        $actual = $sut->fetch('PostSeeder');
        self::assertEquals($expected, $actual);
    }

    private function getConfigArray()
    {
        return array(
            'paths' => array(
                'seeds' => $this->getCorrectedPath(__DIR__ . '/_files/seeds'),
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
            'GSeeder',
            'ParentSeeder',
            'PostSeeder',
            'UserSeeder'
        );
        foreach ($seed_files as $seed_file) {
            /** @noinspection PhpIncludeInspection */
            require_once $config->getSeedPath() . DIRECTORY_SEPARATOR . $seed_file . '.php';
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
        $sut = new SeedFetcher($config);
        $this->includeSeedFiles($config);
        return $sut;
    }

}