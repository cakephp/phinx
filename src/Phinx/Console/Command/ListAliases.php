<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListAliases extends AbstractCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'list:aliases';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('List template class aliases')
            ->setHelp('The <info>list:aliases</info> command lists the migration template generation class aliases');
    }

    /**
     * List migration template creation aliases.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 on success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $aliases = $this->config->getAliases();

        if ($aliases) {
            $maxAliasLength = max(array_map('strlen', array_keys($aliases)));
            $maxClassLength = max(array_map('strlen', $aliases));
            $output->writeln(
                array_merge(
                    [
                        '',
                        sprintf('%s %s', str_pad('Alias', $maxAliasLength), str_pad('Class', $maxClassLength)),
                        sprintf('%s %s', str_repeat('=', $maxAliasLength), str_repeat('=', $maxClassLength)),
                    ],
                    array_map(
                        function ($alias, $class) use ($maxAliasLength, $maxClassLength) {
                            return sprintf('%s %s', str_pad($alias, $maxAliasLength), str_pad($class, $maxClassLength));
                        },
                        array_keys($aliases),
                        $aliases
                    )
                )
            );
        } else {
            $output->writeln(
                sprintf(
                    '<error>No aliases defined in %s</error>',
                    str_replace(getcwd(), '', realpath($this->config->getConfigFilePath()))
                )
            );
        }

        return self::CODE_SUCCESS;
    }
}
