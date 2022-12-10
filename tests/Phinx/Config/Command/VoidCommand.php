<?php

namespace Test\Phinx\Config\Command;

use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class VoidCommand : used to expose locateConfigFile To testing
 *
 * @package Test\Phinx\Config
 */
class VoidCommand extends AbstractCommand
{
    public function locateConfigFile(InputInterface $input): string
    {
        return parent::locateConfigFile($input);
    }
}
