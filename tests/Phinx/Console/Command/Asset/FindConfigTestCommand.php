<?php

namespace Test\Phinx\Console\Command\Asset;

use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

class FindConfigTestCommand extends AbstractCommand
{
    public function configure()
    {
        parent::configure();
        $this->setName('test');
    }

    public function callLocateConfigFile()
    {
        $input = new ArrayInput([]);
        $input->bind($this->getDefinition());
        return $this->locateConfigFile($input);
    }
}

