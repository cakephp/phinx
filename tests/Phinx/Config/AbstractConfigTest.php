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
                    'schema' => '%%PHINX_CONFIG_PATH%%/testmigrations2/schema.sql',
                )
            ),
            'paths' => array(
                'migrations' => $this->getMigrationPath(),
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
     * Generate dummy migration path
     * @return string
     */
    protected function getMigrationPath()
    {
        if (null === $this->migrationPath) {
            $this->migrationPath = uniqid('phinx', true);
        }
        return $this->migrationPath;
    }
}
