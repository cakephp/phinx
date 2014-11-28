<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Config\ConfigInterface;
use Phinx\Migration\Manager\Environment;

class Manager
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    protected $environments;

    /**
     * @var array
     */
    protected $migrations;

    /**
     * Class Constructor.
     *
     * @param ConfigInterface $config Configuration Object
     * @param OutputInterface $output Console Output
     */
    public function __construct(ConfigInterface $config, OutputInterface $output)
    {
        $this->setConfig($config);
        $this->setOutput($output);
    }

    /**
     * Prints the specified environment's migration status.
     *
     * @param string $environment
     * @param null $format
     * @return void
     */
    public function printStatus($environment, $format = null)
    {
        $output = $this->getOutput();
        $migrations = array();
        if (count($this->getMigrations())) {
            $output->writeln('');
            $output->writeln(' Status  Migration ID    Migration Name ');
            $output->writeln('-----------------------------------------');

            $env = $this->getEnvironment($environment);
            $versions = $env->getVersions();

            foreach ($this->getMigrations() as $migration) {
                if (in_array($migration->getVersion(), $versions)) {
                    $status = '     <info>up</info> ';
                    unset($versions[array_search($migration->getVersion(), $versions)]);
                } else {
                    $status = '   <error>down</error> ';
                }

                $output->writeln(
                    $status
                    . sprintf(' %14.0f ', $migration->getVersion())
                    . ' <comment>' . $migration->getName() . '</comment>'
                );
                $migrations[] = array('migration_status' => trim(strip_tags($status)), 'migration_id' => sprintf('%14.0f', $migration->getVersion()), 'migration_name' => $migration->getName());
            }

            foreach ($versions as $missing) {
                $output->writeln(
                    '     <error>up</error> '
                    . sprintf(' %14.0f ', $missing)
                    . ' <error>** MISSING **</error>'
                );
            }
        } else {
            // there are no migrations
            $output->writeln('');
            $output->writeln('There are no available migrations. Try creating one using the <info>create</info> command.');
        }

        // write an empty line
        $output->writeln('');
        if ($format != null) {
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($migrations));
                    break;
                default:
                    $output->writeln('<info>Unsupported format: '.$format.'</info>');
                    break;
            }
        }

    }

    /**
     * Migrate an environment to the specified version.
     *
     * @param string $environment Environment
     * @param int $version
     * @return void
     */
    public function migrate($environment, $version = null)
    {
        $migrations = $this->getMigrations();
        $env = $this->getEnvironment($environment);
        $versions = $env->getVersions();
        $current = $env->getCurrentVersion();

        if (empty($versions) && empty($migrations)) {
            return;
        }

        if (null === $version) {
            $version = max(array_merge($versions, array_keys($migrations)));
        } else {
            if (0 != $version && !isset($migrations[$version])) {
                $this->output->writeln(sprintf(
                    '<comment>warning</comment> %s is not a valid version',
                    $version
                ));
                return;
            }
        }

        // are we migrating up or down?
        $direction = $version > $current ? MigrationInterface::UP : MigrationInterface::DOWN;

        if ($direction == MigrationInterface::DOWN) {
            // run downs first
            krsort($migrations);
            foreach ($migrations as $migration) {
                if ($migration->getVersion() <= $version) {
                    break;
                }

                if (in_array($migration->getVersion(), $versions)) {
                    $this->executeMigration($environment, $migration, MigrationInterface::DOWN);
                }
            }
        }

        ksort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() > $version) {
                break;
            }

            if (!in_array($migration->getVersion(), $versions)) {
                $this->executeMigration($environment, $migration, MigrationInterface::UP);
            }
        }
    }

    /**
     * Execute a migration against the specified Environment.
     *
     * @param string $name Environment Name
     * @param MigrationInterface $migration Migration
     * @param string $direction Direction
     * @return void
     */
    public function executeMigration($name, MigrationInterface $migration, $direction = MigrationInterface::UP)
    {
        $this->getOutput()->writeln('');
        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>'
            . ' <comment>' . ($direction == 'up' ? 'migrating' : 'reverting') . '</comment>'
        );

        // Execute the migration and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment($name)->executeMigration($migration, $direction);
        $end = microtime(true);

        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>'
            . ' <comment>' . ($direction == 'up' ? 'migrated' : 'reverted')
            . ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
    }

    /**
     * Rollback an environment to the specified version.
     *
     * @param string $environment Environment
     * @param int $version
     * @return void
     */
    public function rollback($environment, $version = null)
    {
        $migrations = $this->getMigrations();
        $env = $this->getEnvironment($environment);
        $versions = $env->getVersions();

        ksort($migrations);
        sort($versions);

        // Check we have at least 1 migration to revert
        if (empty($versions) || $version == end($versions)) {
            $this->getOutput()->writeln('<error>No migrations to rollback</error>');
            return;
        }

        // If no target version was supplied, revert the last migration
        if (null === $version) {
            // Get the migration before the last run migration
            $prev = count($versions) - 2;
            $version = $prev >= 0 ? $versions[$prev] : 0;
        } else {
            // Get the first migration number
            $first = reset($versions);

            // If the target version is before the first migration, revert all migrations
            if ($version < $first) {
                $version = 0;
            }
        }

        // Check the target version exists
        if (0 !== $version && !isset($migrations[$version])) {
            $this->getOutput()->writeln("<error>Target version ($version) not found</error>");
            return;
        }

        // Revert the migration(s)
        krsort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() <= $version) {
                break;
            }

            if (in_array($migration->getVersion(), $versions)) {
                $this->executeMigration($environment, $migration, MigrationInterface::DOWN);
            }
        }
    }

    /**
     * Sets the environments.
     *
     * @param array $environments Environments
     * @return Manager
     */
    public function setEnvironments($environments = array())
    {
        $this->environments = $environments;
        return $this;
    }

    /**
     * Gets the manager class for the given environment.
     *
     * @param string $name Environment Name
     * @throws \InvalidArgumentException
     * @return Environment
     */
    public function getEnvironment($name)
    {
        if (isset($this->environments[$name])) {
            return $this->environments[$name];
        }

        // check the environment exists
        if (!$this->getConfig()->hasEnvironment($name)) {
            throw new \InvalidArgumentException(sprintf(
                'The environment "%s" does not exist',
                $name
            ));
        }

        // create an environment instance and cache it
        $environment = new Environment($name, $this->getConfig()->getEnvironment($name));
        $this->environments[$name] = $environment;
        $environment->setOutput($this->getOutput());

        return $environment;
    }

    /**
     * Sets the console output.
     *
     * @param OutputInterface $output Output
     * @return Manager
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Gets the console output.
     *
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Sets the database migrations.
     *
     * @param array $migrations Migrations
     * @return Manager
     */
    public function setMigrations(array $migrations)
    {
        $this->migrations = $migrations;
        return $this;
    }

    /**
     * Gets an array of the database migrations.
     *
     * @throws \InvalidArgumentException
     * @return AbstractMigration[]
     */
    public function getMigrations()
    {
        if (null === $this->migrations) {
            $config = $this->getConfig();
            $phpFiles = glob($config->getMigrationPath() . DIRECTORY_SEPARATOR . '*.php');

            // filter the files to only get the ones that match our naming scheme
            $fileNames = array();
            /** @var AbstractMigration[] $versions */
            $versions = array();

            foreach ($phpFiles as $filePath) {
                if (preg_match('/([0-9]+)_([_a-z0-9]*).php/', basename($filePath))) {
                    $matches = array();
                    preg_match('/^[0-9]+/', basename($filePath), $matches); // get the version from the start of the filename
                    $version = $matches[0];

                    if (isset($versions[$version])) {
                        throw new \InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $versions[$version]->getVersion()));
                    }

                    // convert the filename to a class name
                    $class = preg_replace('/^[0-9]+_/', '', basename($filePath));
                    $class = str_replace('_', ' ', $class);
                    $class = ucwords($class);
                    $class = str_replace(' ', '', $class);
                    if (false !== strpos($class, '.')) {
                        $class = substr($class, 0, strpos($class, '.'));
                    }

                    if (isset($fileNames[$class])) {
                        throw new \InvalidArgumentException(sprintf(
                            'Migration "%s" has the same name as "%s"',
                            basename($filePath),
                            $fileNames[$class]
                        ));
                    }

                    $fileNames[$class] = basename($filePath);

                    // load the migration file
                    /** @noinspection PhpIncludeInspection */
                    require_once $filePath;
                    if (!class_exists($class)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Could not find class "%s" in file "%s"',
                            $class,
                            $filePath
                        ));
                    }

                    // instantiate it
                    $migration = new $class($version);

                    if (!($migration instanceof AbstractMigration)) {
                        throw new \InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Migration\AbstractMigration',
                            $class,
                            $filePath
                        ));
                    }

                    $migration->setOutput($this->getOutput());
                    $versions[$version] = $migration;
                }
            }

            ksort($versions);
            $this->setMigrations($versions);
        }

        return $this->migrations;
    }

    /**
     * Sets the config.
     *
     * @param  ConfigInterface $config Configuration Object
     * @return Manager
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the config.
     *
     * @return ConfigInterface
     */
    public function getConfig()
    {
        return $this->config;
    }
}
