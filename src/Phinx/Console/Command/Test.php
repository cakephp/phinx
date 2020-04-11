<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use InvalidArgumentException;
use Phinx\Migration\Manager\Environment;
use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Leonid Kuzmin <lndkuzmin@gmail.com>
 */
class Test extends AbstractCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'test';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment');

        $this->setDescription('Verify the configuration file')
            ->setHelp(
                <<<EOT
The <info>test</info> command verifies the YAML configuration file and optionally an environment

<info>phinx test</info>
<info>phinx test -e development</info>

EOT
            );
    }

    /**
     * Verify configuration file
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @throws \InvalidArgumentException
     *
     * @return int 0 on success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input, $output);
        $this->loadManager($input, $output);

        // Verify the migrations path(s)
        array_map(
            [$this, 'verifyMigrationDirectory'],
            Util::globAll($this->getConfig()->getMigrationPaths())
        );

        // Verify the seed path(s)
        array_map(
            [$this, 'verifySeedDirectory'],
            Util::globAll($this->getConfig()->getSeedPaths())
        );

        $envName = $input->getOption('environment');
        if ($envName) {
            if (!$this->getConfig()->hasEnvironment($envName)) {
                throw new InvalidArgumentException(sprintf(
                    'The environment "%s" does not exist',
                    $envName
                ));
            }

            $output->writeln(sprintf('<info>validating environment</info> %s', $envName));
            $environment = new Environment(
                $envName,
                $this->getConfig()->getEnvironment($envName)
            );
            // validate environment connection
            $environment->getAdapter()->connect();
        }

        $output->writeln('<info>success!</info>');

        return self::CODE_SUCCESS;
    }
}
