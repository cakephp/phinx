<?php

namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Question\ConfirmationQuestion;

class SchemaLoad extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputArgument::OPTIONAL, 'The target environment');
        $this->addOption('--destroy', '-d', InputArgument::OPTIONAL, 'Destroy database without asking');

        $this->setName('schema:load')
            ->setDescription('Load schema to the database.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');
        $destroy = $input->getOption('destroy');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        $output->writeln('<info>using database</info> ' . $envOptions['name']);

        $schemaName = isset($envOptions["schema_name"]) ? $envOptions["schema_name"] : '';
        $filePath = $this->getManager()->loadSchemaFilePath($schemaName);
        if (!file_exists($filePath)) {
            $output->writeln('<comment>Schema file missing. Nothing to load.</comment>');
            return;
        }

        if(null === $destroy) {
            $helper = $this->getHelperSet()->get('question');
            $question = new ConfirmationQuestion('Hey! You must be pretty damn sure that you want to destroy \''.$envOptions['name'].'\'. Are you sure? (y/n) ', false);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Aborting.');
                return;
            }
        }

        $start = microtime(true);
        $this->getManager()->schemaLoad($environment, $filePath);
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
