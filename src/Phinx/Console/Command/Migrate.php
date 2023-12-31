<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use DateTime;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'migrate')]
class Migrate extends AbstractCommand
{
    /**
     * @var string|null
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected static $defaultName = 'migrate';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment');

        $this->setDescription('Migrate the database')
            ->addOption('--target', '-t', InputOption::VALUE_REQUIRED, 'The version number to migrate to')
            ->addOption('--date', '-d', InputOption::VALUE_REQUIRED, 'The date to migrate to')
            ->addOption('--dry-run', '-x', InputOption::VALUE_NONE, 'Dump query to standard output instead of executing it')
            ->addOption('--fake', null, InputOption::VALUE_NONE, "Mark any migrations selected as run, but don't actually execute them")
            ->setHelp(
                <<<EOT
The <info>migrate</info> command runs all available migrations, optionally up to a specific version

<info>phinx migrate -e development</info>
<info>phinx migrate -e development -t 20110103081132</info>
<info>phinx migrate -e development -d 20110103</info>
<info>phinx migrate -e development -v</info>

EOT
            );
    }

    /**
     * Migrate the database.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return int integer 0 on success, or an error code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        $version = $input->getOption('target') !== null ? (int)$input->getOption('target') : null;
        /** @var string|null $environment */
        $environment = $input->getOption('environment');
        $date = $input->getOption('date');
        $fake = (bool)$input->getOption('fake');

        if ($environment === null) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment, $this->verbosityLevel);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment, $this->verbosityLevel);
        }

        if (!$this->getConfig()->hasEnvironment($environment)) {
            $output->writeln(sprintf('<error>The environment "%s" does not exist</error>', $environment));

            return self::CODE_ERROR;
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $envOptions['adapter'], $this->verbosityLevel);
        }

        if (isset($envOptions['wrapper'])) {
            $output->writeln('<info>using wrapper</info> ' . $envOptions['wrapper'], $this->verbosityLevel);
        }

        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name'], $this->verbosityLevel);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');

            return self::CODE_ERROR;
        }

        if (isset($envOptions['table_prefix'])) {
            $output->writeln('<info>using table prefix</info> ' . $envOptions['table_prefix'], $this->verbosityLevel);
        }
        if (isset($envOptions['table_suffix'])) {
            $output->writeln('<info>using table suffix</info> ' . $envOptions['table_suffix'], $this->verbosityLevel);
        }

        $versionOrder = $this->getConfig()->getVersionOrder();
        $output->writeln('<info>ordering by</info> ' . $versionOrder . ' time', $this->verbosityLevel);

        if ($fake) {
            $output->writeln('<comment>warning</comment> performing fake migrations', $this->verbosityLevel);
        }

        try {
            // run the migrations
            $start = microtime(true);
            if ($date !== null) {
                $this->getManager()->migrateToDateTime($environment, new DateTime($date), $fake);
            } else {
                $this->getManager()->migrate($environment, $version, $fake);
            }
            $end = microtime(true);
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->__toString() . '</error>');

            return self::CODE_ERROR;
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->__toString() . '</error>');

            return self::CODE_ERROR;
        }

        $output->writeln('', $this->verbosityLevel);
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>', $this->verbosityLevel);

        return self::CODE_SUCCESS;
    }
}
