<?php

namespace Test\Phinx\Config;

/**
 * Class AbstractConfigTest
 * @package Test\Phinx\Config
 * @group config
 * @coversNothing
 */
abstract class AbstractConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $migrationPath = null;

    /**
     * @var string
     */
    protected $seedPath = null;

    /**
     * Returns a sample configuration array for use with the unit tests.
     *
     * @return array
     */
    public function getConfigArray()
    {
        return array(
            'default' => array(
                'paths' => array(
                    'migrations' => '%%PHINX_CONFIG_PATH%%/testmigrations2',
                    'seeds' => '%%PHINX_CONFIG_PATH%%/db/seeds',
                )
            ),
            'paths' => array(
                'migrations' => $this->getMigrationPaths(),
                'seeds' => $this->getSeedPaths()
            ),
            'templates' => array(
                'file' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.txt',
                'class' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.php'
            ),
            'environments' => array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'testing',
                'testing' => array(
                    'adapter' => 'sqllite',
                    'wrapper' => 'testwrapper',
                    'path' => '%%PHINX_CONFIG_PATH%%/testdb/test.db'
                ),
                'production' => array(
                    'adapter' => 'mysql'
                )
            )
        );
    }

    /**
     * Generate dummy migration paths
     *
     * @return string[]
     */
    protected function getMigrationPaths()
    {
        if (null === $this->migrationPath) {
            $this->migrationPath = uniqid('phinx', true);
        }

        return array($this->migrationPath);
    }

    /**
     * Generate dummy seed paths
     *
     * @return string[]
     */
    protected function getSeedPaths()
    {
        if (null === $this->seedPath) {
            $this->seedPath = uniqid('phinx', true);
        }

        return array($this->seedPath);
    }
}
