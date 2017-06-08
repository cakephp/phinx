<?php
namespace Phinx\Console\Command\Traits;

use Phinx\Config\ConfigInterface;

trait AbstractCommandTrait
{
    /**
     * @return ConfigInterface
     */
    abstract function getConfig();
}
