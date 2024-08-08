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

        $success = $this->writeInformationOutput($environment, $output);
        if (!$success) {
            return self::CODE_ERROR;
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
