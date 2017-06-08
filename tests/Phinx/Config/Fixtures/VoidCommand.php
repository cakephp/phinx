<?php
namespace Test\Phinx\Config\Fixtures;

use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    /**
     * @inheritDoc
     */
    protected function reportPaths(InputInterface $input, OutputInterface $output)
    {
    }
}
