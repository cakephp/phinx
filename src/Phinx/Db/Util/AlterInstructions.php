<?php

namespace Phinx\Db\Util;

class AlterInstructions
{

    protected $alterParts = [];

    protected $postSteps = [];

    public function __construct(array $alterParts = [], array $postSteps = [])
    {
        $this->alterParts = $alterParts;
        $this->postSteps = $postSteps;
    }

    public function addAlter($part)
    {
        $this->alterParts[] = $part;
    }

    public function addPostStep($sql)
    {
        $this->postSteps[] = $sql;
    }

    public function getAlterParts()
    {
        return $this->alterParts;
    }

    public function getPostSteps()
    {
        return $this->postSteps;
    }

    public function merge(AlterInstructions $other)
    {
        $this->alterParts = array_merge($this->alterParts, $other->getAlterParts());
        $this->postSteps = array_merge($this->postSteps, $other->getPostSteps());
    }

    public function execute($alterTemplate, callable $executor)
    {
        if ($this->alterParts) {
            $alter = sprintf($alterTemplate, implode(', ', $this->alterParts));
            $executor($alter);
        }

        $state = [];

        foreach ($this->postSteps as $instruction) {
            if (is_callable($instruction)) {
                $state = $instruction($state);
                continue;
            }

            $executor($instruction);
        }
    }
}
