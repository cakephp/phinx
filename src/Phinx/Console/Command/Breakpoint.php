<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Console\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'breakpoint')]
class Breakpoint extends AbstractCommand
{
    /**
     * @var string|null
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected static $defaultName = 'breakpoint';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment.');

        $this->setDescription('Manage breakpoints')
            ->addOption('--target', '-t', InputOption::VALUE_REQUIRED, 'The version number to target for the breakpoint')
            ->addOption('--set', '-s', InputOption::VALUE_NONE, 'Set the breakpoint')
            ->addOption('--unset', '-u', InputOption::VALUE_NONE, 'Unset the breakpoint')
            ->addOption('--remove-all', '-r', InputOption::VALUE_NONE, 'Remove all breakpoints')
            ->setHelp(
                <<<EOT
The <info>breakpoint</info> command allows you to toggle, set, or unset a breakpoint against a specific target to inhibit rollbacks beyond a certain target.
If no target is supplied then the most recent migration will be used.
You cannot specify un-migrated targets

<info>phinx breakpoint -e development</info>
<info>phinx breakpoint -e development -t 20110103081132</info>
<info>phinx breakpoint -e development -r</info>
EOT
            );
    }

    /**
     * Toggle the breakpoint.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @throws \InvalidArgumentException
     * @return int integer 0 on success, or an error code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        /** @var string|null $environment */
        $environment = $input->getOption('environment');
        $version = (int)$input->getOption('target') ?: null;
        $removeAll = $input->getOption('remove-all');
        $set = $input->getOption('set');
        $unset = $input->getOption('unset');

        $success = $this->writeEnvironmentOutput($environment, $output);
        if (!$success) {
            return self::CODE_ERROR;
        }

        if ($version && $removeAll) {
            throw new InvalidArgumentException('Cannot toggle a breakpoint and remove all breakpoints at the same time.');
        }

        if (($set && $unset) || ($set && $removeAll) || ($unset && $removeAll)) {
            throw new InvalidArgumentException('Cannot use more than one of --set, --unset, or --remove-all at the same time.');
        }

        if ($removeAll) {
            // Remove all breakpoints.
            $this->getManager()->removeBreakpoints($environment);
        } elseif ($set) {
            // Set the breakpoint.
            $this->getManager()->setBreakpoint($environment, $version);
        } elseif ($unset) {
            // Unset the breakpoint.
            $this->getManager()->unsetBreakpoint($environment, $version);
        } else {
            // Toggle the breakpoint.
            $this->getManager()->toggleBreakpoint($environment, $version);
        }

        return self::CODE_SUCCESS;
    }
}
