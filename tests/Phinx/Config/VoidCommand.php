<?php

namespace Test\Phinx\Config;

use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;

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
