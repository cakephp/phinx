<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Console
 */
namespace Phinx\Console\Command;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class Init extends Command
{

    const TEMPLATE_DIR        = '/../../Config/templates';
    const DEFAULT_CONFIG_NAME = 'phinx';
    const DEFAULT_CONFIG_EXT  = 'yml';

    public $extensionFile = [
      'php' =>  'phinx.template.php.dist',
      'yml' =>  'phinx.template.yml.dist'
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('init')
            ->setDescription('Initialize the application for Phinx')
            ->addArgument('path', InputArgument::OPTIONAL, 'Which path should we initialize for Phinx ?')
            ->addOption('--file-extension', '-f', InputOption::VALUE_REQUIRED, 'The  extension config file.')
            ->addOption('--config-name', '-g', InputOption::VALUE_REQUIRED, 'The  name config file.')
            ->setHelp(sprintf(
                '%sInitializes the application for Phinx%s',
                PHP_EOL,
                PHP_EOL
            ));
    }

    /**
     * Initializes the application.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get the migration path from the config
        $path               = $input->getArgument('path');
        $extension          = $input->getOption('file-extension');
        $configName         = $input->getOption('config-name');

        if (null === $path) {
            $path = getcwd();
        }
        
        if (null === $extension) {
            $extension = self::DEFAULT_CONFIG_EXT;
        }

        if (null === $configName) {
            $configName = self::DEFAULT_CONFIG_NAME;
        }

        $path = realpath($path);
        if (!is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" is not writable',
                $path
            ));
        }

        // Compute the file path
        if (isset($this->extensionFile[$extension])) {
            $fileName = $configName . '.' . $extension;
        } else {
            $fileName = $configName . '.' . self::DEFAULT_CONFIG_EXT;
        }
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        // check config template directory
        if (!is_dir(__DIR__ . self::TEMPLATE_DIR)) {
            throw new Exception('The directory ' . __DIR__ . self::TEMPLATE_DIR . 'not exist.');
        }
        // check config template
        if (!is_file(__DIR__ . self::TEMPLATE_DIR . '/' . $this->extensionFile[$extension])) {
            throw new Exception($this->extensionFile[$extension] . 'Undefined template file.');
        }
        // load config file
        $contents = file_get_contents(__DIR__ . self::TEMPLATE_DIR . '/' . $this->extensionFile[$extension]);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
