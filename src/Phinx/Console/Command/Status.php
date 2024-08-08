<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'status')]
class Status extends AbstractCommand
{
    /**
     * @var string|null
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected static $defaultName = 'status';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment.');

        $this->setDescription('Show migration status')
            ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, 'The output format: text or json. Defaults to text.')
            ->setHelp(
                <<<EOT
The <info>status</info> command prints a list of all migrations, along with their current status

<info>phinx status -e development</info>
<info>phinx status -e development -f json</info>

The <info>version_order</info> configuration option is used to determine the order of the status migrations.
EOT
            );
    }

    /**
     * Show the migration status.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return int 0 if all migrations are up, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        /** @var string|null $environment */
        $environment = $input->getOption('environment');
        /** @var string|null $environment */
        $format = $input->getOption('format');

        $success = $this->writeEnvironmentOutput($environment, $output);
        if (!$success) {
            return self::CODE_ERROR;
        }

        if ($format !== null) {
            $output->writeln('<info>using format</info> ' . $format, $this->verbosityLevel);
        }

        $output->writeln('<info>ordering by </info>' . $this->getConfig()->getVersionOrder() . ' time', $this->verbosityLevel);

        // print the status
        $result = $this->getManager()->printStatus($environment, $format);

        if ($result['hasMissingMigration']) {
            return self::CODE_STATUS_MISSING;
        } elseif ($result['hasDownMigration']) {
            return self::CODE_STATUS_DOWN;
        }

        return self::CODE_SUCCESS;
    }
}
