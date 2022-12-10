<?php

namespace Test\Phinx\Config;

use PHPUnit\Framework\TestCase;

/**
 * Class AbstractConfigTest
 *
 * @package Test\Phinx\Config
 * @group config
 * @coversNothing
 */
abstract class AbstractConfigTest extends TestCase
{
    /**
     * @var string
     */
    protected $migrationPath;

    /**
     * @var string
     */
    protected $seedPath;

    /**
     * Returns a sample configuration array for use with the unit tests.
     *
     * @return array
     */
    public function getConfigArray()
    {
        return [
            'default' => [
                'paths' => [
                    'migrations' => '%%PHINX_CONFIG_PATH%%/testmigrations2',
                    'seeds' => '%%PHINX_CONFIG_PATH%%/db/seeds',
                ],
            ],
            'paths' => [
                'migrations' => $this->getMigrationPaths(),
                'seeds' => $this->getSeedPaths(),
            ],
            'templates' => [
                'file' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.txt',
                'class' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.php',
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'testing',
                'testing' => [
                    'adapter' => 'sqllite',
                    'wrapper' => 'testwrapper',
                    'path' => '%%PHINX_CONFIG_PATH%%/testdb/test.db',
                ],
                'production' => [
                    'adapter' => 'mysql',
                ],
            ],
            'data_domain' => [
                'phone_number' => [
                    'type' => 'string',
                    'null' => true,
                    'length' => 15,
                ],
            ],
        ];
    }

    /**
     * Generate dummy migration paths
     *
     * @return string[]
     */
    protected function getMigrationPaths()
    {
        if ($this->migrationPath === null) {
            $this->migrationPath = uniqid('phinx', true);
        }

        return [$this->migrationPath];
    }

    /**
     * Generate dummy seed paths
     *
     * @return string[]
     */
    protected function getSeedPaths()
    {
        if ($this->seedPath === null) {
            $this->seedPath = uniqid('phinx', true);
        }

        return [$this->seedPath];
    }
}
