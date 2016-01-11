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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Config\ConfigInterface;
use Phinx\Migration\Manager\Environment;
use Phinx\Seed\AbstractSeed;
use Phinx\Seed\SeedInterface;
use Phinx\Util\Util;

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
     * @var array
     */
    protected $seeds;

    /**
     * @var integer
     */
    const EXIT_STATUS_DOWN = 1;

    /**
     * @var integer
     */
    const EXIT_STATUS_MISSING = 2;

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
     * @return integer 0 if all migrations are up, or an error code
     */
    public function printStatus($environment, $format = null)
    {
        $output = $this->getOutput();
        $migrations = array();
        $hasDownMigration = false;
        $hasMissingMigration = false;
        if (count($this->getMigrations())) {
            // TODO - rewrite using Symfony Table Helper as we already have this library
            // included and it will fix formatting issues (e.g drawing the lines)
            $output->writeln('');
            $output->writeln(' Status  Migration ID    Started              Finished             Migration Name ');
            $output->writeln('----------------------------------------------------------------------------------');

            $env = $this->getEnvironment($environment);
            $versions = $env->getVersionLog();
            $maxNameLength = $versions ? max(array_map(function($version) {
                return strlen($version['migration_name']);
            }, $versions)) : 0;

            foreach ($this->getMigrations() as $migration) {
                $version = array_key_exists($migration->getVersion(), $versions) ? $versions[$migration->getVersion()] : false;
                if ($version) {
                    $status = '     <info>up</info> ';
                } else {
                    $hasDownMigration = true;
                    $status = '   <error>down</error> ';
                }
                $maxNameLength = max($maxNameLength, strlen($migration->getName()));

                $output->writeln(sprintf(
                    '%s %14.0f  %19s  %19s  <comment>%s</comment>',
                    $status, $migration->getVersion(), $version['start_time'], $version['end_time'], $migration->getName()
                ));
                $migrations[] = array('migration_status' => trim(strip_tags($status)), 'migration_id' => sprintf('%14.0f', $migration->getVersion()), 'migration_name' => $migration->getName());
                unset($versions[$migration->getVersion()]);
            }

            if (count($versions)) {
                $hasMissingMigration = true;
                foreach ($versions as $missing => $version) {
                    $output->writeln(sprintf(
                        '     <error>up</error>  %14.0f  %19s  %19s  <comment>%s</comment>  <error>** MISSING **</error>',
                        $missing, $version['start_time'], $version['end_time'], str_pad($version['migration_name'], $maxNameLength, ' ')
                    ));
                }
            }
        } else {
            // there are no migrations
            $output->writeln('');
            $output->writeln('There are no available migrations. Try creating one using the <info>create</info> command.');
        }

        // write an empty line
        $output->writeln('');
        if ($format !== null) {
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode(
                        array(
                            'pending_count' => count($this->getMigrations()),
                            'migrations' => $migrations
                        )
                    ));
                    break;
                default:
                    $output->writeln('<info>Unsupported format: '.$format.'</info>');
            }
        }

        if ($hasMissingMigration) {
            return self::EXIT_STATUS_MISSING;
        } else if ($hasDownMigration) {
            return self::EXIT_STATUS_DOWN;
        } else {
            return 0;
        }
    }

    /**
     * Migrate to the version of the database on a given date.
     *
     * @param string    $environment Environment
     * @param \DateTime $dateTime    Date to migrate to
     *
     * @return void
     */
    public function migrateToDateTime($environment, \DateTime $dateTime)
    {
        $versions   = array_keys($this->getMigrations());
        $dateString = $dateTime->format('YmdHis');

        $outstandingMigrations = array_filter($versions, function($version) use($dateString) {
            return $version <= $dateString;
        });

        if (count($outstandingMigrations) > 0) {
            $migration = max($outstandingMigrations);
            $this->getOutput()->writeln('Migrating to version ' . $migration);
            $this->migrate($environment, $migration);
        }
    }

    /**
     * Roll back to the version of the database on a given date.
     *
     * @param string    $environment Environment
     * @param \DateTime $dateTime    Date to roll back to
     *
     * @return void
     */
    public function rollbackToDateTime($environment, \DateTime $dateTime)
    {
        $env        = $this->getEnvironment($environment);
        $versions   = $env->getVersions();
        $dateString = $dateTime->format('YmdHis');
        sort($versions);

        $earlierVersion = null;
        $availableMigrations = array_filter($versions, function($version) use($dateString, &$earlierVersion) {
            if ($version <= $dateString) {
                $earlierVersion = $version;
            }
            return $version >= $dateString;
        });

        if (count($availableMigrations) > 0) {
            if (is_null($earlierVersion)) {
                $this->getOutput()->writeln('Rolling back all migrations');
                $migration = 0;
            } else {
                $this->getOutput()->writeln('Rolling back to version ' . $earlierVersion);
                $migration = $earlierVersion;
            }
            $this->rollback($environment, $migration);
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

        if ($direction === MigrationInterface::DOWN) {
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
     * Execute a migration against the specified environment.
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
            . ' <comment>' . ($direction === MigrationInterface::UP ? 'migrating' : 'reverting') . '</comment>'
        );

        // Execute the migration and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment($name)->executeMigration($migration, $direction);
        $end = microtime(true);

        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>'
            . ' <comment>' . ($direction === MigrationInterface::UP ? 'migrated' : 'reverted')
            . ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
    }

    /**
     * Execute a seeder against the specified environment.
     *
     * @param string $name Environment Name
     * @param SeedInterface $seed Seed
     * @return void
     */
    public function executeSeed($name, SeedInterface $seed)
    {
        $this->getOutput()->writeln('');
        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $seed->getName() . ':</info>'
            . ' <comment>seeding</comment>'
        );

        // Execute the seeder and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment($name)->executeSeed($seed);
        $end = microtime(true);

        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $seed->getName() . ':</info>'
            . ' <comment>seeded'
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
     * Run database seeders against an environment.
     *
     * @param string $environment Environment
     * @param string $seed Seeder
     * @return void
     */
    public function seed($environment, $seed = null)
    {
        $seeds = $this->getSeeds();
        $env = $this->getEnvironment($environment);

        if (null === $seed) {
            // run all seeders
            foreach ($seeds as $seeder) {
                if (array_key_exists($seeder->getName(), $seeds)) {
                    $this->executeSeed($environment, $seeder);
                }
            }
        } else {
            // run only one seeder
            if (array_key_exists($seed, $seeds)) {
                $this->executeSeed($environment, $seeds[$seed]);
            } else {
                throw new \InvalidArgumentException(sprintf('The seed class "%s" does not exist', $seed));
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
            $phpFiles = glob($config->getMigrationPath() . DIRECTORY_SEPARATOR . '*.php', GLOB_BRACE);

            // filter the files to only get the ones that match our naming scheme
            $fileNames = array();
            /** @var AbstractMigration[] $versions */
            $versions = array();

            foreach ($phpFiles as $filePath) {
                if (Util::isValidMigrationFileName(basename($filePath))) {
                    $version = Util::getVersionFromFileName(basename($filePath));

                    if (isset($versions[$version])) {
                        throw new \InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $versions[$version]->getVersion()));
                    }

                    // convert the filename to a class name
                    $class = Util::mapFileNameToClassName(basename($filePath));

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
     * Sets the database seeders.
     *
     * @param array $seeds Seeders
     * @return Manager
     */
    public function setSeeds(array $seeds)
    {
        $this->seeds = $seeds;
        return $this;
    }

    /**
     * Gets an array of database seeders.
     *
     * @throws \InvalidArgumentException
     * @return AbstractSeed[]
     */
    public function getSeeds()
    {
        if (null === $this->seeds) {
            $config = $this->getConfig();
            $phpFiles = glob($config->getSeedPath() . DIRECTORY_SEPARATOR . '*.php');

            // filter the files to only get the ones that match our naming scheme
            $fileNames = array();
            /** @var AbstractSeed[] $seeds */
            $seeds = array();

            foreach ($phpFiles as $filePath) {
                if (Util::isValidSeedFileName(basename($filePath))) {
                    // convert the filename to a class name
                    $class = pathinfo($filePath, PATHINFO_FILENAME);
                    $fileNames[$class] = basename($filePath);

                    // load the seed file
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
                    $seed = new $class();

                    if (!($seed instanceof AbstractSeed)) {
                        throw new \InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Seed\AbstractSeed',
                            $class,
                            $filePath
                        ));
                    }

                    $seed->setOutput($this->getOutput());
                    $seeds[$class] = $seed;
                }
            }

            ksort($seeds);
            $this->setSeeds($seeds);
        }

        return $this->seeds;
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
