<?php
namespace Phinx\Seed;

use Phinx\Config\ConfigInterface;
use Phinx\Util\Util;
use Symfony\Component\Console\Output\OutputInterface;

class SeedFetcher
{
    private $config;
    private $outputInterface;

    public function __construct(ConfigInterface $config, OutputInterface $output)
    {
        $this->config = $config;
        $this->outputInterface = $output;
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
    public function fetch($seed = null)
    {
        if(null === $seed) {
            $seed = '*';
        }
        $files = [];
        foreach($this->config->getSeedPaths() as $path) {
            $files = array_merge($files, glob($path . DIRECTORY_SEPARATOR . $seed . '.php'));
        }

        $queue = array();

        foreach ($files as $filePath) {
            if (Util::isValidSeedFileName(basename($filePath))) {
                $class = pathinfo($filePath, PATHINFO_FILENAME);

                if (!array_key_exists($class, $queue)) {
                    $seed = $this->instantiateClass($filePath, $class);
                    $heap = $this->getParentStack($filePath, $class, $seed);
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
        $seed->setOutput($this->outputInterface);
        return $seed;
    }

    /**
     * @param $filePath
     * @param string $class
     * @param AbstractSeed $seed
     * @return \SplStack
     */
    private function getParentStack($filePath, $class, AbstractSeed $seed)
    {
        $heap = new \SplStack();
        $heap->push(array($class => $seed));
        $parent = $seed->getParentSeed();
        while(null !== $parent) {
            $seed = $this->instantiateClass($filePath, $parent);
            $heap->push(array($parent => $seed));
            $parent = $seed->getParentSeed();
        }
        return $heap;
    }



}