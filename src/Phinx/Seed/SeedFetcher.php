<?php
namespace Phinx\Seed;

use Phinx\Config\Config;
use Phinx\Util\Util;

class SeedFetcher
{
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the Seeders in the proper order
     *
     *   Alphabetically if they don't have dependencies, and inserting the dependency before it's needed
     * in case they have.
     *
     * @param string|null $seed
     * @return AbstractSeed[]
     */
    public function fetch($seed = '*')
    {
        $files = glob($this->config->getSeedPath() . DIRECTORY_SEPARATOR . $seed . '.php');

        $queue = array();

        foreach ($files as $filePath) {
            if (Util::isValidSeedFileName(basename($filePath))) {
                $class = pathinfo($filePath, PATHINFO_FILENAME);

                if (!array_key_exists($class, $queue)) {
                    $seed = $this->instantiateClass($filePath, $class);
                    $heap = $this->getParentStack($class, $seed);
                    while ($heap->count()) {
                        $element_to_queue = $heap->pop();
                        $queue[key($element_to_queue)] = current($element_to_queue);
                    }
                }
            }
        }
        return $queue;
    }

    /**
     * @param string $filePath
     * @param string $class
     * @throws \InvalidArgumentException
     * @return AbstractSeed
     */
    private function instantiateClass($filePath, $class)
    {
        /** @noinspection PhpIncludeInspection */
        require_once $filePath;
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf(
                'Could not find class "%s" in file "%s"',
                $class,
                $filePath
            ));
        }

        $seed = new $class();
        if (!($seed instanceof AbstractSeed)) {
            throw new \InvalidArgumentException(sprintf(
                'The class "%s" in file "%s" must extend \Phinx\Seed\AbstractSeed',
                $class,
                $filePath
            ));
        }
        return $seed;
    }

    /**
     * @param string $class
     * @param AbstractSeed $seed
     * @throws \InvalidArgumentException
     * @return \SplStack
     */
    private function getParentStack($class, AbstractSeed $seed)
    {
        $heap = new \SplStack();
        $heap->push(array($class => $seed));
        $parent = $seed->getParentSeed();
        while(null !== $parent) {
            $seed = $this->instantiateClass($this->config->getSeedPath() . DIRECTORY_SEPARATOR . $parent .'.php', $parent);
            $heap->push(array($parent => $seed));
            $parent = $seed->getParentSeed();
        }
        return $heap;
    }



}