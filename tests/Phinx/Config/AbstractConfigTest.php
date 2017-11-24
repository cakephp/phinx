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
    protected $migrationPathWithMultiDb = null;

    /**
     * @var string
     */
    protected $migrationPathWithMultiDbAsString = null;

    /**
     * @var string
     */
    protected $seedPath = null;

    /**
     * @var string
     */
    protected $seedPathWithMultiDb = null;

    /**
     * @var string
     */
    protected $seedPathWithMultiDbAsString = null;

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
                ]
            ],
            'paths' => [
                'migrations' => $this->getMigrationPaths(),
                'seeds' => $this->getSeedPaths()
            ],
            'templates' => [
                'file' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.txt',
                'class' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.php'
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_database' => 'testing',
                'testing' => [
                    'adapter' => 'sqllite',
                    'wrapper' => 'testwrapper',
                    'path' => '%%PHINX_CONFIG_PATH%%/testdb/test.db'
                ],
                'production' => [
                    'adapter' => 'mysql'
                ]
            ]
        ];
    }

    public function getConfigArrayWithMultiDb()
    {
        return [
            'default' => [
                'paths' => [
                    'migrations' => '%%PHINX_CONFIG_PATH%%/testmigrations2',
                    'seeds' => '%%PHINX_CONFIG_PATH%%/db/seeds',
                ]
            ],
            'paths' => [
                'migrations' => $this->getMigrationPaths(),
                'seeds' => $this->getSeedPaths()
            ],
            'templates' => [
                'file' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.txt',
                'class' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.php'
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_database' => 'testing',
                'testing' => [
                    'db1' => [
                        'adapter' => 'sqllite',
                        'wrapper' => 'testwrapper',
                        'paths' => [
                            'migrations' => $this->getMigrationPathsWithMultiDb(),
                            'seeds' => $this->getSeedPathsWithMultiDb()
                        ],
                    ]
                ],
                'production' => [
                    'db1' => [
                        'adapter' => 'mysql',
                        'paths' => [
                            'migrations' => $this->getMigrationPathsWithMultiDbAsString(),
                            'seeds' => $this->getSeedPathsWithMultiDbAsString()
                        ],
                    ]
                ]
            ]
        ];
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

        return [$this->migrationPath];
    }

    protected function getMigrationPathsWithMultiDb()
    {
        if (null === $this->migrationPathWithMultiDb) {
            $this->migrationPathWithMultiDb = uniqid('phinx', true);
        }

        return [$this->migrationPathWithMultiDb];
    }

    protected function getMigrationPathsWithMultiDbAsString()
    {
        if (null === $this->migrationPathWithMultiDbAsString) {
            $this->migrationPathWithMultiDbAsString = uniqid('phinx', true);
        }

        return $this->migrationPathWithMultiDbAsString;
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

        return [$this->seedPath];
    }

    /**
     * Generate dummy seed paths
     *
     * @return string[]
     */
    protected function getSeedPathsWithMultiDb()
    {
        if (null === $this->seedPathWithMultiDb) {
            $this->seedPathWithMultiDb = uniqid('phinx', true);
        }

        return [$this->seedPathWithMultiDb];
    }

    /**
     * Generate dummy seed paths
     *
     * @return string[]
     */
    protected function getSeedPathsWithMultiDbAsString()
    {
        if (null === $this->seedPathWithMultiDbAsString) {
            $this->seedPathWithMultiDbAsString = uniqid('phinx', true);
        }

        return $this->seedPathWithMultiDbAsString;
    }
}
