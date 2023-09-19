<?php
declare(strict_types=1);

namespace Test\Phinx\Migration;

use DateTime;
use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\Manager;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Test\Phinx\Console\Output\RawBufferedOutput;
use Test\Phinx\TestCase;

class ManagerTest extends TestCase
{
    /**
     * @var \Phinx\Config\Config
     */
    protected $config;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface $input
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected $output;

    /**
     * @var Manager
     */
    private $manager;

    protected function setUp(): void
    {
        $this->config = new Config($this->getConfigArray());
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        $this->manager = new Manager($this->config, $this->input, $this->output);
    }

    protected function getConfigWithNamespace($paths = [])
    {
        if (empty($paths)) {
            $paths = [
                'migrations' => [
                    'Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/migrations'),
                ],
                'seeds' => [
                    'Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/seeds'),
                ],
            ];
        }
        $config = clone $this->config;
        $config['paths'] = $paths;

        return $config;
    }

    protected function getConfigWithMixedNamespace($paths = [])
    {
        if (empty($paths)) {
            $paths = [
                'migrations' => [
                    $this->getCorrectedPath(__DIR__ . '/_files/migrations'),
                    'Baz' => $this->getCorrectedPath(__DIR__ . '/_files_baz/migrations'),
                    'Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/migrations'),
                ],
                'seeds' => [
                    $this->getCorrectedPath(__DIR__ . '/_files/seeds'),
                    'Baz' => $this->getCorrectedPath(__DIR__ . '/_files_baz/seeds'),
                    'Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/seeds'),
                ],
            ];
        }
        $config = clone $this->config;
        $config['paths'] = $paths;

        return $config;
    }

    protected function tearDown(): void
    {
        $this->manager = null;
    }

    private function getCorrectedPath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Returns a sample configuration array for use with the unit tests.
     *
     * @return array
     */
    public function getConfigArray()
    {
        return [
            'paths' => [
                'migrations' => $this->getCorrectedPath(__DIR__ . '/_files/migrations'),
                'seeds' => $this->getCorrectedPath(__DIR__ . '/_files/seeds'),
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'production',
                'production' => defined('MYSQL_DB_CONFIG') ? MYSQL_DB_CONFIG : [],
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
     * Prepares an environment for cross DBMS functional tests.
     *
     * @param array $paths The paths config to override.
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    protected function prepareEnvironment(array $paths = []): AdapterInterface
    {
        $configArray = $this->getConfigArray();

        // override paths as needed
        if ($paths) {
            $configArray['paths'] = $paths + $configArray['paths'];
        }
        $configArray['environments']['production'] = DB_CONFIG;
        $this->manager->setConfig(new Config($configArray));

        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // ensure the database is empty
        if (DB_CONFIG['adapter'] === 'pgsql') {
            $adapter->dropSchema('public');
            $adapter->createSchema('public');
        } elseif (DB_CONFIG['name'] !== ':memory:') {
            $adapter->dropDatabase(DB_CONFIG['name']);
            $adapter->createDatabase(DB_CONFIG['name']);
        }
        $adapter->disconnect();

        return $adapter;
    }

    public function testInstantiation()
    {
        $this->assertInstanceOf(
            'Symfony\Component\Console\Output\StreamOutput',
            $this->manager->getOutput()
        );
    }

    public function testEnvironmentInheritsDataDomainOptions()
    {
        foreach ($this->config->getEnvironments() as $name => $opts) {
            $env = $this->manager->getEnvironment($name);
            $this->assertArrayHasKey('data_domain', $env->getOptions());
        }
    }

    public function testPrintStatusMethod()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('up  20120111235330  2012-01-11 23:53:36  2012-01-11 23:53:37  TestMigration', $outputStr);
        $this->assertStringContainsString('up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2', $outputStr);
    }

    public function testPrintStatusMethodJsonFormat()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                    ]
                ));
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv', AbstractCommand::FORMAT_JSON);
        $this->assertSame(['hasMissingMigration' => false, 'hasDownMigration' => false], $return);
        rewind($this->manager->getOutput()->getStream());
        $outputStr = trim(stream_get_contents($this->manager->getOutput()->getStream()));
        $this->assertEquals('{"pending_count":0,"missing_count":0,"total_count":2,"migrations":[{"migration_status":"up","migration_id":"20120111235330","migration_name":"TestMigration"},{"migration_status":"up","migration_id":"20120116183504","migration_name":"TestMigration2"}]}', $outputStr);
    }

    public function testPrintStatusMethodWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20160111235330' =>
                            [
                                'version' => '20160111235330',
                                'start_time' => '2016-01-11 23:53:36',
                                'end_time' => '2016-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160116183504' =>
                            [
                                'version' => '20160116183504',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('up  20160111235330  2016-01-11 23:53:36  2016-01-11 23:53:37  Foo\\Bar\\TestMigration', $outputStr);
        $this->assertStringContainsString('up  20160116183504  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\Bar\\TestMigration2', $outputStr);
    }

    public function testPrintStatusMethodWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20150111235330' =>
                            [
                                'version' => '20150111235330',
                                'start_time' => '2015-01-11 23:53:36',
                                'end_time' => '2015-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20150116183504' =>
                            [
                                'version' => '20150116183504',
                                'start_time' => '2015-01-16 18:35:40',
                                'end_time' => '2015-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160111235330' =>
                            [
                                'version' => '20160111235330',
                                'start_time' => '2016-01-11 23:53:36',
                                'end_time' => '2016-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160116183504' =>
                            [
                                'version' => '20160116183504',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('up  20120111235330  2012-01-11 23:53:36  2012-01-11 23:53:37  TestMigration', $outputStr);
        $this->assertStringContainsString('up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2', $outputStr);
        $this->assertStringContainsString('up  20150111235330  2015-01-11 23:53:36  2015-01-11 23:53:37  Baz\\TestMigration', $outputStr);
        $this->assertStringContainsString('up  20150116183504  2015-01-16 18:35:40  2015-01-16 18:35:41  Baz\\TestMigration2', $outputStr);
        $this->assertStringContainsString('up  20160111235330  2016-01-11 23:53:36  2016-01-11 23:53:37  Foo\\Bar\\TestMigration', $outputStr);
        $this->assertStringContainsString('up  20160116183504  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\Bar\\TestMigration2', $outputStr);
    }

    public function testPrintStatusMethodWithBreakpointSet()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '1',
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('BREAKPOINT SET', $outputStr);
    }

    public function testPrintStatusMethodWithNoMigrations()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();

        // override the migrations directory to an empty one
        $configArray = $this->getConfigArray();
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/nomigrations');
        $config = new Config($configArray);

        $this->manager->setConfig($config);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('There are no available migrations. Try creating one using the create command.', $outputStr);
    }

    public function testPrintStatusMethodWithMissingMigrations()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120103083300' =>
                            [
                                'version' => '20120103083300',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20120815145812' =>
                            [
                                'version' => '20120815145812',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertMatchesRegularExpression('/\s*up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*up  20120815145812  2012-01-16 18:35:40  2012-01-16 18:35:41  Example   *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*down  20120111235330                                            TestMigration' . PHP_EOL .
            '\s*down  20120116183504                                            TestMigration2/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingMigrationsWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20160103083300' =>
                            [
                                'version' => '20160103083300',
                                'start_time' => '2016-01-11 23:53:36',
                                'end_time' => '2016-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160815145812' =>
                            [
                                'version' => '20160815145812',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertMatchesRegularExpression('/\s*up  20160103083300  2016-01-11 23:53:36  2016-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*up  20160815145812  2016-01-16 18:35:40  2016-01-16 18:35:41  Example   *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*down  20160111235330                                            Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*down  20160116183504                                            Foo\\\\Bar\\\\TestMigration2/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingMigrationsWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20160103083300' =>
                            [
                                'version' => '20160103083300',
                                'start_time' => '2016-01-11 23:53:36',
                                'end_time' => '2016-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160815145812' =>
                            [
                                'version' => '20160815145812',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertMatchesRegularExpression('/\s*up  20160103083300  2016-01-11 23:53:36  2016-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*up  20160815145812  2016-01-16 18:35:40  2016-01-16 18:35:41  Example   *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*down  20120111235330                                            TestMigration' . PHP_EOL .
            '\s*down  20120116183504                                            TestMigration2' . PHP_EOL .
            '\s*down  20150111235330                                            Baz\\\\TestMigration' . PHP_EOL .
            '\s*down  20150116183504                                            Baz\\\\TestMigration2' . PHP_EOL .
            '\s*down  20160111235330                                            Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*down  20160116183504                                            Foo\\\\Bar\\\\TestMigration2/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingLastMigration()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20120120145114' =>
                            [
                                'version' => '20120120145114',
                                'start_time' => '2012-01-20 14:51:14',
                                'end_time' => '2012-01-20 14:51:14',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertMatchesRegularExpression('/\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration' . PHP_EOL .
            '\s*up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2' . PHP_EOL .
            '\s*up  20120120145114  2012-01-20 14:51:14  2012-01-20 14:51:14  Example   *\*\* MISSING MIGRATION FILE \*\*/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingLastMigrationWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20160111235330' =>
                            [
                                'version' => '20160111235330',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                        '20160116183504' =>
                            [
                                'version' => '20160116183504',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160120145114' =>
                            [
                                'version' => '20160120145114',
                                'start_time' => '2016-01-20 14:51:14',
                                'end_time' => '2016-01-20 14:51:14',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertMatchesRegularExpression('/\s*up  20160111235330  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*up  20160116183504  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\\\Bar\\\\TestMigration2' . PHP_EOL .
            '\s*up  20160120145114  2016-01-20 14:51:14  2016-01-20 14:51:14  Example   *\*\* MISSING MIGRATION FILE \*\*/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingLastMigrationWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20150111235330' =>
                            [
                                'version' => '20150111235330',
                                'start_time' => '2015-01-16 18:35:40',
                                'end_time' => '2015-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                        '20150116183504' =>
                            [
                                'version' => '20150116183504',
                                'start_time' => '2015-01-16 18:35:40',
                                'end_time' => '2015-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20160111235330' =>
                            [
                                'version' => '20160111235330',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                        '20160116183504' =>
                            [
                                'version' => '20160116183504',
                                'start_time' => '2016-01-16 18:35:40',
                                'end_time' => '2016-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20170120145114' =>
                            [
                                'version' => '20170120145114',
                                'start_time' => '2017-01-20 14:51:14',
                                'end_time' => '2017-01-20 14:51:14',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => false], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertMatchesRegularExpression(
            '/\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration' . PHP_EOL .
            '\s*up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2' . PHP_EOL .
            '\s*up  20150111235330  2015-01-16 18:35:40  2015-01-16 18:35:41  Baz\\\\TestMigration' . PHP_EOL .
            '\s*up  20150116183504  2015-01-16 18:35:40  2015-01-16 18:35:41  Baz\\\\TestMigration2' . PHP_EOL .
            '\s*up  20160111235330  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*up  20160116183504  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\\\Bar\\\\TestMigration2' . PHP_EOL .
            '\s*up  20170120145114  2017-01-20 14:51:14  2017-01-20 14:51:14  Example   *\*\* MISSING MIGRATION FILE \*\*/',
            $outputStr
        );
    }

    public function testPrintStatusMethodWithMissingMigrationsAndBreakpointSet()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120103083300' =>
                            [
                                'version' => '20120103083300',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '1',
                            ],
                        '20120815145812' =>
                            [
                                'version' => '20120815145812',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertMatchesRegularExpression('/up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*/', $outputStr);
        $this->assertStringContainsString('BREAKPOINT SET', $outputStr);
        $this->assertMatchesRegularExpression('/up  20120815145812  2012-01-16 18:35:40  2012-01-16 18:35:41  Example   *\*\* MISSING MIGRATION FILE \*\*/', $outputStr);
    }

    public function testPrintStatusMethodWithDownMigrations()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20120111235330' => [
                        'version' => '20120111235330',
                        'start_time' => '2012-01-16 18:35:40',
                        'end_time' => '2012-01-16 18:35:41',
                        'migration_name' => '',
                        'breakpoint' => 0,
                    ]]));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration', $outputStr);
        $this->assertStringContainsString('down  20120116183504                                            TestMigration2', $outputStr);
    }

    public function testPrintStatusMethodWithDownMigrationsWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20160111235330' => [
                        'version' => '20160111235330',
                        'start_time' => '2016-01-16 18:35:40',
                        'end_time' => '2016-01-16 18:35:41',
                        'migration_name' => '',
                        'breakpoint' => 0,
                    ]]));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('up  20160111235330  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\Bar\\TestMigration', $outputStr);
        $this->assertStringContainsString('down  20160116183504                                            Foo\\Bar\\TestMigration2', $outputStr);
    }

    public function testPrintStatusMethodWithDownMigrationsWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                        '20120116183504' =>
                            [
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                        '20150111235330' =>
                            [
                                'version' => '20150111235330',
                                'start_time' => '2015-01-16 18:35:40',
                                'end_time' => '2015-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0,
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertMatchesRegularExpression(
            '/\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration' . PHP_EOL .
            '\s*up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2' . PHP_EOL .
            '\s*up  20150111235330  2015-01-16 18:35:40  2015-01-16 18:35:41  Baz\\\\TestMigration/',
            $outputStr
        );
        $this->assertMatchesRegularExpression(
            '/\s*down  20150116183504                                            Baz\\\\TestMigration2' . PHP_EOL .
            '\s*down  20160111235330                                            Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*down  20160116183504                                            Foo\\\\Bar\\\\TestMigration2/',
            $outputStr
        );
    }

    public function testPrintStatusMethodWithMissingAndDownMigrations()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20120111235330' =>
                        [
                            'version' => '20120111235330',
                            'start_time' => '2012-01-16 18:35:40',
                            'end_time' => '2012-01-16 18:35:41',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                    '20120103083300' =>
                        [
                            'version' => '20120103083300',
                            'start_time' => '2012-01-11 23:53:36',
                            'end_time' => '2012-01-11 23:53:37',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                    '20120815145812' =>
                        [
                            'version' => '20120815145812',
                            'start_time' => '2012-01-16 18:35:40',
                            'end_time' => '2012-01-16 18:35:41',
                            'migration_name' => 'Example',
                            'breakpoint' => 0,
                        ]]));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations (and in the right
        // place with regard to other up non-missing migrations)
        $this->assertMatchesRegularExpression('/\s*up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration' . PHP_EOL .
            '\s*down  20120116183504                                            TestMigration2/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingAndDownMigrationsWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20160111235330' =>
                        [
                            'version' => '20160111235330',
                            'start_time' => '2016-01-16 18:35:40',
                            'end_time' => '2016-01-16 18:35:41',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                    '20160103083300' =>
                        [
                            'version' => '20160103083300',
                            'start_time' => '2016-01-11 23:53:36',
                            'end_time' => '2016-01-11 23:53:37',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ]]));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations (and in the right
        // place with regard to other up non-missing migrations)
        $this->assertMatchesRegularExpression('/\s*up  20160103083300  2016-01-11 23:53:36  2016-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*up  20160111235330  2016-01-16 18:35:40  2016-01-16 18:35:41  Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*down  20160116183504                                            Foo\\\\Bar\\\\TestMigration2/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingAndDownMigrationsWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20120103083300' =>
                        [
                            'version' => '20120103083300',
                            'start_time' => '2012-01-11 23:53:36',
                            'end_time' => '2012-01-11 23:53:37',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                    '20120111235330' =>
                        [
                            'version' => '20120111235330',
                            'start_time' => '2012-01-16 18:35:40',
                            'end_time' => '2012-01-16 18:35:41',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                    '20120116183504' =>
                        [
                            'version' => '20120116183504',
                            'start_time' => '2012-01-16 18:35:43',
                            'end_time' => '2012-01-16 18:35:44',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                    '20150111235330' =>
                        [
                            'version' => '20150111235330',
                            'start_time' => '2015-01-16 18:35:40',
                            'end_time' => '2015-01-16 18:35:41',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ],
                ]));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => true, 'hasDownMigration' => true], $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());

        // note that the order is important: missing migrations should appear before down migrations (and in the right
        // place with regard to other up non-missing migrations)
        $this->assertMatchesRegularExpression('/\s*up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING MIGRATION FILE \*\*' . PHP_EOL .
            '\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration' . PHP_EOL .
            '\s*up  20120116183504  2012-01-16 18:35:43  2012-01-16 18:35:44  TestMigration2' . PHP_EOL .
            '\s*up  20150111235330  2015-01-16 18:35:40  2015-01-16 18:35:41  Baz\\\\TestMigration' . PHP_EOL .
            '\s*down  20150116183504                                            Baz\\\\TestMigration2' . PHP_EOL .
            '\s*down  20160111235330                                            Foo\\\\Bar\\\\TestMigration' . PHP_EOL .
            '\s*down  20160116183504                                            Foo\\\\Bar\\\\TestMigration2/', $outputStr);
    }

    /**
     * Test that ensures the status header is correctly printed with regards to the version order
     *
     * @dataProvider statusVersionOrderProvider
     * @param Config $config Config to use for the test
     * @param string $expectedStatusHeader expected header string
     */
    public function testPrintStatusMethodVersionOrderHeader($config, $expectedStatusHeader)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue([]));

        $output = new RawBufferedOutput();
        $this->manager = new Manager($config, $this->input, $output);

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(['hasMissingMigration' => false, 'hasDownMigration' => true], $return);

        $outputStr = $this->manager->getOutput()->fetch();
        $this->assertStringContainsString($expectedStatusHeader, $outputStr);
    }

    public function statusVersionOrderProvider()
    {
        // create the necessary configuration objects
        $configArray = $this->getConfigArray();

        $configWithNoVersionOrder = new Config($configArray);

        $configArray['version_order'] = Config::VERSION_ORDER_CREATION_TIME;
        $configWithCreationVersionOrder = new Config($configArray);

        $configArray['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $configWithExecutionVersionOrder = new Config($configArray);

        return [
            'With the default version order' => [
                $configWithNoVersionOrder,
                ' Status  <info>[Migration ID]</info>  Started              Finished             Migration Name ',
            ],
            'With the creation version order' => [
                $configWithCreationVersionOrder,
                ' Status  <info>[Migration ID]</info>  Started              Finished             Migration Name ',
            ],
            'With the execution version order' => [
                $configWithExecutionVersionOrder,
                ' Status  Migration ID    <info>[Started          ]</info>  Finished             Migration Name ',
            ],
        ];
    }

    public function testPrintStatusInvalidVersionOrderKO()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();

        $configArray = $this->getConfigArray();
        $configArray['version_order'] = 'invalid';
        $config = new Config($configArray);

        $this->manager = new Manager($config, $this->input, $this->output);

        $this->manager->setEnvironments(['mockenv' => $envStub]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid version_order configuration option');

        $this->manager->printStatus('mockenv');
    }

    public function testGetMigrationsWithDuplicateMigrationVersions()
    {
        $config = new Config(['paths' => ['migrations' => $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions')]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate migration - "' . $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions/20120111235330_duplicate_migration_2.php') . '" has the same version as "20120111235330"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithDuplicateMigrationVersionsWithNamespace()
    {
        $config = new Config(['paths' => ['migrations' => ['Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/duplicateversions')]]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate migration - "' . $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/duplicateversions/20160111235330_duplicate_migration_2.php') . '" has the same version as "20160111235330"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithDuplicateMigrationVersionsWithMixedNamespace()
    {
        $config = new Config(['paths' => [
            'migrations' => [
                $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions_mix_ns'),
                'Baz' => $this->getCorrectedPath(__DIR__ . '/_files_baz/duplicateversions_mix_ns'),
            ],
        ]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate migration - "' . $this->getCorrectedPath(__DIR__ . '/_files_baz/duplicateversions_mix_ns/20120111235330_duplicate_migration_mixed_namespace_2.php') . '" has the same version as "20120111235330"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithDuplicateMigrationNames()
    {
        $config = new Config(['paths' => ['migrations' => $this->getCorrectedPath(__DIR__ . '/_files/duplicatenames')]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Migration "20120111235331_duplicate_migration_name.php" has the same name as "20120111235330_duplicate_migration_name.php"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithDuplicateMigrationNamesWithNamespace()
    {
        $config = new Config(['paths' => ['migrations' => ['Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/duplicatenames')]]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Migration "20160111235331_duplicate_migration_name.php" has the same name as "20160111235330_duplicate_migration_name.php"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithInvalidMigrationClassName()
    {
        $config = new Config(['paths' => ['migrations' => $this->getCorrectedPath(__DIR__ . '/_files/invalidclassname')]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find class "InvalidClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files/invalidclassname/20120111235330_invalid_class.php') . '"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithInvalidMigrationClassNameWithNamespace()
    {
        $config = new Config(['paths' => ['migrations' => ['Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/invalidclassname')]]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find class "Foo\Bar\InvalidClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/invalidclassname/20160111235330_invalid_class.php') . '"');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithClassThatDoesntExtendAbstractMigration()
    {
        $config = new Config(['paths' => ['migrations' => $this->getCorrectedPath(__DIR__ . '/_files/invalidsuperclass')]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "InvalidSuperClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files/invalidsuperclass/20120111235330_invalid_super_class.php') . '" must extend \Phinx\Migration\AbstractMigration');

        $manager->getMigrations('mockenv');
    }

    public function testGetMigrationsWithClassThatDoesntExtendAbstractMigrationWithNamespace()
    {
        $config = new Config(['paths' => ['migrations' => ['Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/invalidsuperclass')]]]);
        $manager = new Manager($config, $this->input, $this->output);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "Foo\Bar\InvalidSuperClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/invalidsuperclass/20160111235330_invalid_super_class.php') . '" must extend \Phinx\Migration\AbstractMigration');

        $manager->getMigrations('mockenv');
    }

    public function testGettingAValidEnvironment()
    {
        $this->assertInstanceOf(
            'Phinx\Migration\Manager\Environment',
            $this->manager->getEnvironment('production')
        );
    }

    /**
     * Test that migrating by date chooses the correct
     * migration to point to.
     *
     * @dataProvider migrateDateDataProvider
     * @param string[] $availableMigrations
     * @param string $dateString
     * @param string $expectedMigration
     * @param string $message
     */
    public function testMigrationsByDate(array $availableMigrations, $dateString, $expectedMigration, $message)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        if (is_null($expectedMigration)) {
            $envStub->expects($this->never())
                    ->method('getVersions');
        } else {
            $envStub->expects($this->once())
                    ->method('getVersions')
                    ->will($this->returnValue($availableMigrations));
        }
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->migrateToDateTime('mockenv', new DateTime($dateString));
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedMigration)) {
            $this->assertEmpty($output, $message);
        } else {
            $this->assertStringContainsString($expectedMigration, $output, $message);
        }
    }

    /**
     * Test that rollbacking to version chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToVersionDataProvider
     */
    public function testRollbackToVersion($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to version chooses the correct
     * migration (with namespace) to point to.
     *
     * @dataProvider rollbackToVersionDataProviderWithNamespace
     */
    public function testRollbackToVersionWithNamespace($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setConfig($this->getConfigWithNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to version chooses the correct
     * migration (with mixed namespace) to point to.
     *
     * @dataProvider rollbackToVersionDataProviderWithMixedNamespace
     */
    public function testRollbackToVersionWithMixedNamespace($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to date chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToDateDataProvider
     */
    public function testRollbackToDate($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to date chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToDateDataProviderWithNamespace
     */
    public function testRollbackToDateWithNamespace($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setConfig($this->getConfigWithNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to date chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToDateDataProviderWithMixedNamespace
     */
    public function testRollbackToDateWithMixedNamespace($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to version by execution time chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToVersionByExecutionTimeDataProvider
     */
    public function testRollbackToVersionByExecutionTime($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $configArray = $this->getConfigArray();
        $configArray['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $config = new Config($configArray);
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);

        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        if (is_null($expectedOutput)) {
            $this->assertEmpty($output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to version by migration name chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToVersionByExecutionTimeDataProvider
     */
    public function testRollbackToVersionByName($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $configArray = $this->getConfigArray();
        $configArray['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $config = new Config($configArray);
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);

        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $availableRollbacks[$version]['migration_name'] ?? $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        if (is_null($expectedOutput)) {
            $this->assertEmpty($output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to version by execution time chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToVersionByExecutionTimeDataProviderWithNamespace
     */
    public function testRollbackToVersionByExecutionTimeWithNamespace($availableRollbacks, $version, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $config = $this->getConfigWithNamespace();
        $config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);

        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        if (is_null($expectedOutput)) {
            $this->assertEmpty($output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to date by execution time chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackToDateByExecutionTimeDataProvider
     */
    public function testRollbackToDateByExecutionTime($availableRollbacks, $date, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $configArray = $this->getConfigArray();
        $configArray['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $config = new Config($configArray);
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);

        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $date, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking to date by execution time chooses the correct
     * migration (with namespace) to point to.
     *
     * @dataProvider rollbackToDateByExecutionTimeDataProviderWithNamespace
     */
    public function testRollbackToDateByExecutionTimeWithNamespace($availableRollbacks, $date, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $config = $this->getConfigWithNamespace();
        $config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);

        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', $date, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    public function testRollbackToVersionWithSingleMigrationDoesNotFail()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                ]));
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will($this->returnValue([20120111235330]));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('== 20120111235330 TestMigration: reverting', $output);
        $this->assertStringContainsString('== 20120111235330 TestMigration: reverted', $output);
        $this->assertStringNotContainsString('No migrations to rollback', $output);
        $this->assertStringNotContainsString('Undefined offset: -1', $output);
    }

    public function testRollbackToVersionWithTwoMigrationsDoesNotRollbackBothMigrations()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
                ->method('getVersionLog')
                ->will(
                    $this->returnValue(
                        [
                            '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                            '20120116183504' => ['version' => '20120815145812', 'migration' => '', 'breakpoint' => 0],
                        ]
                    )
                );
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will(
                    $this->returnValue(
                        [
                            20120111235330,
                            20120116183504,
                        ]
                    )
                );

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringNotContainsString('== 20120111235330 TestMigration: reverting', $output);
    }

    public function testRollbackToVersionWithTwoMigrationsDoesNotRollbackBothMigrationsWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
                ->method('getVersionLog')
                ->will(
                    $this->returnValue(
                        [
                            '20160111235330' => ['version' => '20160111235330', 'migration' => '', 'breakpoint' => 0],
                            '20160116183504' => ['version' => '20160815145812', 'migration' => '', 'breakpoint' => 0],
                        ]
                    )
                );
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will(
                    $this->returnValue(
                        [
                            20160111235330,
                            20160116183504,
                        ]
                    )
                );

        $this->manager->setConfig($this->getConfigWithNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringNotContainsString('== 20160111235330 Foo\Bar\TestMigration: reverting', $output);
    }

    public function testRollbackToVersionWithTwoMigrationsDoesNotRollbackBothMigrationsWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
                ->method('getVersionLog')
                ->will(
                    $this->returnValue(
                        [
                            '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                            '20150116183504' => ['version' => '20150116183504', 'migration' => '', 'breakpoint' => 0],
                        ]
                    )
                );
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will(
                    $this->returnValue(
                        [
                            20120111235330,
                            20150116183504,
                        ]
                    )
                );

        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('== 20150116183504 Baz\TestMigration2: reverting', $output);
        $this->assertStringNotContainsString('== 20160111235330 TestMigration: reverting', $output);
    }

    /**
     * Test that rollbacking last migration
     *
     * @dataProvider rollbackLastDataProvider
     */
    public function testRollbackLast($availableRolbacks, $versionOrder, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRolbacks));

        // get a manager with a config whose version order is set to execution time
        $configArray = $this->getConfigArray();
        $configArray['version_order'] = $versionOrder;
        $config = new Config($configArray);
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', null);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking last migration
     *
     * @dataProvider rollbackLastDataProviderWithNamespace
     */
    public function testRollbackLastWithNamespace($availableRolbacks, $versionOrder, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRolbacks));

        // get a manager with a config whose version order is set to execution time
        $config = $this->getConfigWithNamespace();
        $config['version_order'] = $versionOrder;
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', null);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Test that rollbacking last migration
     *
     * @dataProvider rollbackLastDataProviderWithMixedNamespace
     */
    public function testRollbackLastWithMixedNamespace($availableRolbacks, $versionOrder, $expectedOutput)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRolbacks));

        // get a manager with a config whose version order is set to execution time
        $config = $this->getConfigWithMixedNamespace();
        $config['version_order'] = $versionOrder;
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->rollback('mockenv', null);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals('No migrations to rollback' . PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }

            foreach ($expectedOutput as $expectedLine) {
                $this->assertStringContainsString($expectedLine, $output);
            }
        }
    }

    /**
     * Migration lists, dates, and expected migrations to point to.
     *
     * @return array
     */
    public function migrateDateDataProvider()
    {
        return [
            [['20120111235330', '20120116183504'], '20120118', '20120116183504', 'Failed to migrate all migrations when migrate to date is later than all the migrations'],
            [['20120111235330', '20120116183504'], '20120115', '20120111235330', 'Failed to migrate 1 migration when the migrate to date is between 2 migrations'],
            [['20120111235330', '20120116183504'], '20120111235330', '20120111235330', 'Failed to migrate 1 migration when the migrate to date is one of the migrations'],
            [['20120111235330', '20120116183504'], '20110115', null, 'Failed to migrate 0 migrations when the migrate to date is before all the migrations'],
        ];
    }

    /**
     * Migration lists, dates, and expected migration version to rollback to.
     *
     * @return array
     */
    public function rollbackToDateDataProvider()
    {
        return [

            // No breakpoints set

            'Rollback to date which is later than all migrations - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20130118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120115',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to date of the oldest migration - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to date before all the migrations - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115',
                    ['== 20120116183504 TestMigration2: reverted', '== 20120111235330 TestMigration: reverted'],
                ],

            // Breakpoint set on first migration

            'Rollback to date which is later than all migrations - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20130118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120115',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to date of the oldest migration - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to date before all the migrations - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            // Breakpoint set on last migration

            'Rollback to date which is later than all migrations - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20130118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date of the oldest migration - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date before all the migrations - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            // Breakpoint set on all migrations

            'Rollback to date which is later than all migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20130118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date of the oldest migration - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date before all the migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
        ];
    }

    /**
     * Migration (with namespace) lists, dates, and expected migration version to rollback to.
     *
     * @return array
     */
    public function rollbackToDateDataProviderWithNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to date which is later than all migrations - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160115',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date of the oldest migration - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date before all the migrations - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115',
                    ['== 20160116183504 Foo\Bar\TestMigration2: reverted', '== 20160111235330 Foo\Bar\TestMigration: reverted'],
                ],

            // Breakpoint set on first migration

            'Rollback to date which is later than all migrations - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160115',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date of the oldest migration - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date before all the migrations - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            // Breakpoint set on last migration

            'Rollback to date which is later than all migrations - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date of the oldest migration - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date before all the migrations - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            // Breakpoint set on all migrations

            'Rollback to date which is later than all migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date of the oldest migration - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date before all the migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
        ];
    }

    /**
     * Migration (with mixed namespace) lists, dates, and expected migration version to rollback to.
     *
     * @return array
     */
    public function rollbackToDateDataProviderWithMixedNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to date which is later than all migrations - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160115',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date of the oldest migration - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date before all the migrations - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115',
                    [
                        '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                        '== 20160111235330 Foo\Bar\TestMigration: reverted',
                        '== 20150116183504 Baz\TestMigration2: reverted',
                        '== 20150111235330 Baz\TestMigration: reverted',
                        '== 20120116183504 TestMigration2: reverted',
                        '== 20120111235330 TestMigration: reverted',
                    ],
                ],

            // Breakpoint set on first migration

            'Rollback to date which is later than all migrations - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160115',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date of the oldest migration - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to date before all the migrations - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            // Breakpoint set on last migration

            'Rollback to date which is later than all migrations - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date of the oldest migration - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date before all the migrations - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            // Breakpoint set on all migrations

            'Rollback to date which is later than all migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160118000000',
                    null,
                ],
            'Rollback to date of the most recent migration - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback to date between 2 migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date of the oldest migration - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date before all the migrations - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
        ];
    }

    /**
     * Migration lists, dates, and expected migration version to rollback to.
     *
     * @return array
     */
    public function rollbackToDateByExecutionTimeDataProvider()
    {
        return [

            // No breakpoints set

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20131212000000',
                    null,
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20111212000000',
                    ['== 20120111235330 TestMigration: reverted', '== 20120116183504 TestMigration2: reverted'],
                ],
            'Rollback to start time of first created version which was the last to be executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20120120235330',
                    null,
                ],
            'Rollback to start time of second created version which was the first to be executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20120117183504',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20120118000000',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted',
                ],

            // Breakpoint set on first/last created/executed migration

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20131212000000',
                    null,
                ],
            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20131212000000',
                    null,
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20111212000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20111212000000',
                    ['== 20120111235330 TestMigration: reverted', 'Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20120120235330',
                    null,
                ],
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20120120235330',
                    null,
                ],
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20120117183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20120117183504',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20120118000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20120118000000',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted',
                ],

            // Breakpoint set on all migration

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20131212000000',
                    null,
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20111212000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20120120235330',
                    null,
                ],
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20120117183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20120118000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
        ];
    }

    /**
     * Migration (with namespace) lists, dates, and expected migration version to rollback to.
     *
     * @return array
     */
    public function rollbackToDateByExecutionTimeDataProviderWithNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20161212000000',
                    null,
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20111212000000',
                    ['== 20160111235330 Foo\Bar\TestMigration: reverted', '== 20160116183504 Foo\Bar\TestMigration2: reverted'],
                ],
            'Rollback to start time of first created version which was the last to be executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160120235330',
                    null,
                ],
            'Rollback to start time of second created version which was the first to be executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160117183504',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160118000000',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],

            // Breakpoint set on first/last created/executed migration

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20161212000000',
                    null,
                ],
            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20161212000000',
                    null,
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20111212000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20111212000000',
                    ['== 20160111235330 Foo\Bar\TestMigration: reverted', 'Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160120235330',
                    null,
                ],
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160120235330',
                    null,
                ],
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160117183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160117183504',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160118000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160118000000',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],

            // Breakpoint set on all migration

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20161212000000',
                    null,
                ],
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20111212000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160120235330',
                    null,
                ],
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160117183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160118000000',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
        ];
    }

    /**
     * Migration lists, dates, and expected output.
     *
     * @return array
     */
    public function rollbackToVersionDataProvider()
    {
        return [

            // No breakpoints set

            'Rollback to one of the versions - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to the latest version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    ['== 20120111235330 TestMigration: reverted', '== 20120116183504 TestMigration2: reverted'],
                ],
            'Rollback last version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to non-existing version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - no breakpoints set' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on first migration

            'Rollback to one of the versions - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to the latest version - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback last version - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to non-existing version - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on first migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on last migration

            'Rollback to one of the versions - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to the latest version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on all migrations

            'Rollback to one of the versions - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to the latest version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],
        ];
    }

    /**
     * Migration with namespace lists, dates, and expected output.
     *
     * @return array
     */
    public function rollbackToVersionDataProviderWithNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to one of the versions - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to the latest version - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    ['== 20160111235330 Foo\Bar\TestMigration: reverted', '== 20160116183504 Foo\Bar\TestMigration2: reverted'],
                ],
            'Rollback last version - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to non-existing version - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - no breakpoints set' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on first migration

            'Rollback to one of the versions - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to the latest version - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback last version - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to non-existing version - breakpoint set on first migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on first migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on last migration

            'Rollback to one of the versions - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to the latest version - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last version - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on last migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on all migrations

            'Rollback to one of the versions - breakpoint set on last migration ' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to the latest version - breakpoint set on last migration ' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration ' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last version - breakpoint set on last migration ' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on last migration ' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last migration ' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],
        ];
    }

    /**
     * Migration with mixed namespace lists, dates, and expected output.
     *
     * @return array
     */
    public function rollbackToVersionDataProviderWithMixedNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to one of the versions - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to the latest version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    [
                        '== 20120111235330 TestMigration: reverted',
                        '== 20120116183504 TestMigration2: reverted',
                        '== 20150111235330 Baz\TestMigration: reverted',
                        '== 20150116183504 Baz\TestMigration2: reverted',
                        '== 20160111235330 Foo\Bar\TestMigration: reverted',
                        '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                    ],
                ],
            'Rollback last version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to non-existing version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - no breakpoints set' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on first migration

            'Rollback to one of the versions - breakpoint set on first migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330',
                    [
                        '== 20120116183504 TestMigration2: reverted',
                        '== 20150111235330 Baz\TestMigration: reverted',
                        '== 20150116183504 Baz\TestMigration2: reverted',
                        '== 20160111235330 Foo\Bar\TestMigration: reverted',
                        '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                    ],
                ],

            'Rollback to one of the versions - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to the latest version - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback last version - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to non-existing version - breakpoint set on penultimate migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on first migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on last migration

            'Rollback to one of the versions - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to the latest version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on last migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],

            // Breakpoint set on all migrations

            'Rollback to one of the versions - breakpoint set on last migration ' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to the latest version - breakpoint set on last migration ' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    null,
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration ' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last version - breakpoint set on last migration ' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on last migration ' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last migration ' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'migration_name' => '', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'migration_name' => '', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20111225000000',
                    'Target version (20111225000000) not found',
                ],
        ];
    }

    public function rollbackToVersionByExecutionTimeDataProvider()
    {
        return [

            // No breakpoints set

            'Rollback to first created version with was also the first to be executed - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to last created version which was also the last to be executed - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '0',
                    ['== 20120111235330 TestMigration: reverted', '== 20120116183504 TestMigration2: reverted'],
                ],
            'Rollback to second created version which was the first to be executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to first created version which was the second to be executed - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback last executed version which was the first created version - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to non-existing version - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - no breakpoints set' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration3'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],

            // Breakpoint set on first migration

            'Rollback to first created version with was also the first to be executed - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '0',
                    ['== 20120116183504 TestMigration2: reverted', 'Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on first executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on first created migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on first executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'No migrations to rollback',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on first created migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    '== 20120116183504 TestMigration2: reverted',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on first executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on first created migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on first executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on first executed migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration3'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],

            // Breakpoint set on last migration

            'Rollback to first created version with was also the first to be executed - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '0',
                    ['Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on last executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on last created migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on last executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'No migrations to rollback',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on last created migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on last executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on last created migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted',
                ],
            'Rollback to non-existing version - breakpoint set on last executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last executed migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 0, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0, 'migration_name' => 'TestMigration2'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration3'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],

            // Breakpoint set on all migrations

            'Rollback to first created version with was also the first to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '0',
                    ['Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120116183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20120111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on all migrations' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 1, 'migration_name' => 'TestMigration1'],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1, 'migration_name' => 'TestMigration2'],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1, 'migration_name' => 'TestMigration3'],
                    ],
                    '20121225000000',
                    'Target version (20121225000000) not found',
                ],
        ];
    }

    public function rollbackToVersionByExecutionTimeDataProviderWithNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to first created version with was also the first to be executed - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to last created version which was also the last to be executed - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    '0',
                    ['== 20160111235330 Foo\Bar\TestMigration: reverted', '== 20160116183504 Foo\Bar\TestMigration2: reverted'],
                ],
            'Rollback to second created version which was the first to be executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'end_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to first created version which was the second to be executed - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback last executed version which was the first created version - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to non-existing version - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - no breakpoints set' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],

            // Breakpoint set on first migration

            'Rollback to first created version with was also the first to be executed - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    '0',
                    ['== 20160116183504 Foo\Bar\TestMigration2: reverted', 'Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on first executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'end_time' => '2016-01-10 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on first created migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'end_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on first executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    'No migrations to rollback',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on first created migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on first (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on first executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on first created migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on first executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on first executed migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],

            // Breakpoint set on last migration

            'Rollback to first created version with was also the first to be executed - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    '0',
                    ['Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on last executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'end_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on last created migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'end_time' => '2016-01-10 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160116183504',
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on last executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'No migrations to rollback',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on last created migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    '20160111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on last (executed and created) migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on last executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on last created migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 0],
                    ],
                    null,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],
            'Rollback to non-existing version - breakpoint set on last executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last executed migration' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],

            // Breakpoint set on all migrations

            'Rollback to first created version with was also the first to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    'No migrations to rollback',
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    '0',
                    ['Breakpoint reached. Further rollbacks inhibited.'],
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'end_time' => '2016-01-10 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160116183504',
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20160111235330',
                    'No migrations to rollback',
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'end_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback last executed version which was the first created version - breakpoint set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            'Rollback to non-existing version - breakpoint set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on all migrations' =>
                [
                    [
                        '20111225000000' => ['version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-17 18:35:04', 'end_time' => '2016-01-17 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-20 23:53:30', 'end_time' => '2016-01-20 23:53:30', 'breakpoint' => 1],
                    ],
                    '20161225000000',
                    'Target version (20161225000000) not found',
                ],
        ];
    }

    /**
     * Migration lists, version order configuration and expected output.
     *
     * @return array
     */
    public function rollbackLastDataProvider()
    {
        return [

            // No breakpoints set

            'Rollback to last migration with creation time version ordering - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted',
                ],

            'Rollback to last migration with execution time version ordering - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - no breakpoints set' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted',
                ],

            // Breakpoint set on last migration

            'Rollback to last migration with creation time version ordering - breakpoint set on last created migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with creation time version ordering - breakpoint set on last executed migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on last non-missing created migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on last non-missing executed migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on missing migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on missing migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted',
                ],

            // Breakpoint set on all migrations

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            ];
    }

    /**
     * Migration (with namespace) lists, version order configuration and expected output.
     *
     * @return array
     */
    public function rollbackLastDataProviderWithNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to last migration with creation time version ordering - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with execution time version ordering - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - no breakpoints set' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - no breakpoints set' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20130101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],

            // Breakpoint set on last migration

            'Rollback to last migration with creation time version ordering - breakpoint set on last created migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with creation time version ordering - breakpoint set on last executed migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on last non-missing created migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on last non-missing executed migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on missing migration' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on missing migration' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20160111235330 Foo\Bar\TestMigration: reverted',
                ],

            // Breakpoint set on all migrations

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations ' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-16 18:35:04', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2016-01-10 18:35:04', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2016-01-12 23:53:30', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            ];
    }

    /**
     * Migration (with mixed namespace) lists, version order configuration and expected output.
     *
     * @return array
     */
    public function rollbackLastDataProviderWithMixedNamespace()
    {
        return [

            // No breakpoints set

            'Rollback to last migration with creation time version ordering - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with execution time version ordering - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:06', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:07', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20150116183504 Baz\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - no breakpoints set' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - no breakpoints set' =>
                [
                    [
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:06', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:07', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120116183504 TestMigration2: reverted',
                ],

            // Breakpoint set on last migration

            'Rollback to last migration with creation time version ordering - breakpoint set on last created migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with creation time version ordering - breakpoint set on last executed migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on last non-missing created migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on last non-missing executed migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on missing migration' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    '== 20160116183504 Foo\Bar\TestMigration2: reverted',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on missing migration' =>
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 0],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 0],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 0],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 0],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:06', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted',
                ],

            // Breakpoint set on all migrations

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations ' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],

            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on all migrations' =>
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2017-01-01 00:00:00', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2017-01-01 00:00:01', 'breakpoint' => 1],
                        '20150111235330' => ['version' => '20150111235330', 'start_time' => '2017-01-01 00:00:02', 'breakpoint' => 1],
                        '20150116183504' => ['version' => '20150116183504', 'start_time' => '2017-01-01 00:00:03', 'breakpoint' => 1],
                        '20160111235330' => ['version' => '20160111235330', 'start_time' => '2017-01-01 00:00:04', 'breakpoint' => 1],
                        '20160116183504' => ['version' => '20160116183504', 'start_time' => '2017-01-01 00:00:05', 'breakpoint' => 1],
                        '20170101225232' => ['version' => '20170101225232', 'start_time' => '2017-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.',
                ],
            ];
    }

    public function testExecuteSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('GSeeder', $output);
        $this->assertStringContainsString('PostSeeder', $output);
        $this->assertStringContainsString('UserSeeder', $output);
    }

    public function testExecuteSeedWorksAsExpectedWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setConfig($this->getConfigWithNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('Foo\Bar\GSeeder', $output);
        $this->assertStringContainsString('Foo\Bar\PostSeeder', $output);
        $this->assertStringContainsString('Foo\Bar\UserSeeder', $output);
    }

    public function testExecuteSeedWorksAsExpectedWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('GSeeder', $output);
        $this->assertStringContainsString('PostSeeder', $output);
        $this->assertStringContainsString('UserSeeder', $output);
        $this->assertStringContainsString('Baz\GSeeder', $output);
        $this->assertStringContainsString('Baz\PostSeeder', $output);
        $this->assertStringContainsString('Baz\UserSeeder', $output);
        $this->assertStringContainsString('Foo\Bar\GSeeder', $output);
        $this->assertStringContainsString('Foo\Bar\PostSeeder', $output);
        $this->assertStringContainsString('Foo\Bar\UserSeeder', $output);
    }

    public function testExecuteASingleSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv', 'UserSeeder');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('UserSeeder', $output);
    }

    public function testExecuteASingleSeedWorksAsExpectedWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setConfig($this->getConfigWithNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv', 'Foo\Bar\UserSeeder');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('Foo\Bar\UserSeeder', $output);
    }

    public function testExecuteASingleSeedWorksAsExpectedWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv', 'Baz\UserSeeder');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('Baz\UserSeeder', $output);
    }

    public function testExecuteANonExistentSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setEnvironments(['mockenv' => $envStub]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "NonExistentSeeder" does not exist');

        $this->manager->seed('mockenv', 'NonExistentSeeder');
    }

    public function testExecuteANonExistentSeedWorksAsExpectedWithNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setConfig($this->getConfigWithNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "Foo\Bar\NonExistentSeeder" does not exist');

        $this->manager->seed('mockenv', 'Foo\Bar\NonExistentSeeder');
    }

    public function testExecuteANonExistentSeedWorksAsExpectedWithMixedNamespace()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setConfig($this->getConfigWithMixedNamespace());
        $this->manager->setEnvironments(['mockenv' => $envStub]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "Baz\NonExistentSeeder" does not exist');

        $this->manager->seed('mockenv', 'Baz\NonExistentSeeder');
    }

    public function testOrderSeeds()
    {
        $seeds = array_values($this->manager->getSeeds('mockenv'));
        $this->assertInstanceOf('UserSeeder', $seeds[0]);
        $this->assertInstanceOf('GSeeder', $seeds[1]);
        $this->assertInstanceOf('PostSeeder', $seeds[2]);
    }

    public function testSeedWillNotBeExecuted()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->seed('mockenv', 'UserSeederNotExecuted');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertStringContainsString('skipped', $output);
    }

    public function testGettingInputObject()
    {
        $migrations = $this->manager->getMigrations('mockenv');
        $seeds = $this->manager->getSeeds('mockenv');
        $inputObject = $this->manager->getInput();
        $this->assertInstanceOf('\Symfony\Component\Console\Input\InputInterface', $inputObject);

        foreach ($migrations as $migration) {
            $this->assertEquals($inputObject, $migration->getInput());
        }
        foreach ($seeds as $seed) {
            $this->assertEquals($inputObject, $seed->getInput());
        }
    }

    public function testGettingOutputObject()
    {
        $migrations = $this->manager->getMigrations('mockenv');
        $seeds = $this->manager->getSeeds('mockenv');
        $outputObject = $this->manager->getOutput();
        $this->assertInstanceOf('\Symfony\Component\Console\Output\OutputInterface', $outputObject);

        foreach ($migrations as $migration) {
            $this->assertEquals($outputObject, $migration->getOutput());
        }
        foreach ($seeds as $seed) {
            $this->assertEquals($outputObject, $seed->getOutput());
        }
    }

    public function testGettingAnInvalidEnvironment()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The environment "invalidenv" does not exist');

        $this->manager->getEnvironment('invalidenv');
    }

    public function testReversibleMigrationsWorkAsExpected()
    {
        $adapter = $this->prepareEnvironment([
            'migrations' => $this->getCorrectedPath(__DIR__ . '/_files/reversiblemigrations'),
        ]);

        // migrate to the latest version
        $this->manager->migrate('production');

        // ensure up migrations worked
        $this->assertFalse($adapter->hasTable('info'));
        $this->assertTrue($adapter->hasTable('statuses'));
        $this->assertTrue($adapter->hasTable('users'));
        $this->assertTrue($adapter->hasTable('just_logins'));
        $this->assertFalse($adapter->hasTable('user_logins'));
        $this->assertTrue($adapter->hasColumn('users', 'biography'));
        $this->assertTrue($adapter->hasForeignKey('just_logins', ['user_id']));
        $this->assertTrue($adapter->hasTable('change_direction_test'));
        $this->assertTrue($adapter->hasColumn('change_direction_test', 'subthing'));
        $this->assertEquals(
            2,
            count($adapter->fetchAll('SELECT * FROM change_direction_test WHERE subthing IS NOT NULL'))
        );

        // revert all changes to the first
        $this->manager->rollback('production', '20121213232502');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('info'));
        $this->assertFalse($adapter->hasTable('statuses'));
        $this->assertFalse($adapter->hasTable('user_logins'));
        $this->assertFalse($adapter->hasTable('just_logins'));
        $this->assertTrue($adapter->hasColumn('users', 'bio'));
        $this->assertFalse($adapter->hasForeignKey('user_logins', ['user_id']));
        $this->assertFalse($adapter->hasTable('change_direction_test'));

        // revert all changes
        $this->manager->rollback('production', '0');

        $this->assertFalse($adapter->hasTable('info'));
        $this->assertFalse($adapter->hasTable('users'));
    }

    public function testReversibleMigrationWithIndexConflict()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/drop_index_regression');
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // ensure up migrations worked
        $this->assertTrue($adapter->hasTable('my_table'));
        $this->assertTrue($adapter->hasTable('my_other_table'));
        $this->assertTrue($adapter->hasColumn('my_table', 'entity_id'));
        $this->assertTrue($adapter->hasForeignKey('my_table', ['entity_id']));

        // revert all changes to the first
        $this->manager->rollback('production', '20121213232502');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('my_table'));
        $this->assertTrue($adapter->hasTable('my_other_table'));
        $this->assertTrue($adapter->hasColumn('my_table', 'entity_id'));
        $this->assertFalse($adapter->hasForeignKey('my_table', ['entity_id']));
        $this->assertFalse($adapter->hasIndex('my_table', ['entity_id']));
    }

    public function testReversibleMigrationWithFKConflictOnTableDrop()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/drop_table_with_fk_regression');
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // ensure up migrations worked
        $this->assertTrue($adapter->hasTable('orders'));
        $this->assertTrue($adapter->hasTable('customers'));
        $this->assertTrue($adapter->hasColumn('orders', 'order_date'));
        $this->assertTrue($adapter->hasColumn('orders', 'customer_id'));
        $this->assertTrue($adapter->hasForeignKey('orders', ['customer_id']));

        // revert all changes to the first
        $this->manager->rollback('production', '20190928205056');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('orders'));
        $this->assertTrue($adapter->hasColumn('orders', 'order_date'));
        $this->assertFalse($adapter->hasColumn('orders', 'customer_id'));
        $this->assertFalse($adapter->hasTable('customers'));
        $this->assertFalse($adapter->hasForeignKey('orders', ['customer_id']));

        $this->manager->rollback('production');
        $this->assertFalse($adapter->hasTable('orders'));
        $this->assertFalse($adapter->hasTable('customers'));
    }

    public function testReversibleMigrationsWorkAsExpectedWithNamespace()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = ['Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/reversiblemigrations')];
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // ensure up migrations worked
        $this->assertFalse($adapter->hasTable('info_foo_bar'));
        $this->assertTrue($adapter->hasTable('statuses_foo_bar'));
        $this->assertTrue($adapter->hasTable('users_foo_bar'));
        $this->assertTrue($adapter->hasTable('user_logins_foo_bar'));
        $this->assertTrue($adapter->hasColumn('users_foo_bar', 'biography'));
        $this->assertTrue($adapter->hasForeignKey('user_logins_foo_bar', ['user_id']));

        // revert all changes to the first
        $this->manager->rollback('production', '20161213232502');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('info_foo_bar'));
        $this->assertFalse($adapter->hasTable('statuses_foo_bar'));
        $this->assertFalse($adapter->hasTable('user_logins_foo_bar'));
        $this->assertTrue($adapter->hasColumn('users_foo_bar', 'bio'));
        $this->assertFalse($adapter->hasForeignKey('user_logins_foo_bar', ['user_id']));
    }

    public function testReversibleMigrationsWorkAsExpectedWithMixedNamespace()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = [
            $this->getCorrectedPath(__DIR__ . '/_files/reversiblemigrations'),
            'Baz' => $this->getCorrectedPath(__DIR__ . '/_files_baz/reversiblemigrations'),
            'Foo\Bar' => $this->getCorrectedPath(__DIR__ . '/_files_foo_bar/reversiblemigrations'),
        ];
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // ensure up migrations worked
        $this->assertFalse($adapter->hasTable('info'));
        $this->assertTrue($adapter->hasTable('statuses'));
        $this->assertTrue($adapter->hasTable('users'));
        $this->assertFalse($adapter->hasTable('user_logins'));
        $this->assertTrue($adapter->hasTable('just_logins'));
        $this->assertTrue($adapter->hasColumn('users', 'biography'));
        $this->assertTrue($adapter->hasForeignKey('just_logins', ['user_id']));

        $this->assertFalse($adapter->hasTable('info_baz'));
        $this->assertTrue($adapter->hasTable('statuses_baz'));
        $this->assertTrue($adapter->hasTable('users_baz'));
        $this->assertTrue($adapter->hasTable('user_logins_baz'));
        $this->assertTrue($adapter->hasColumn('users_baz', 'biography'));
        $this->assertTrue($adapter->hasForeignKey('user_logins_baz', ['user_id']));

        $this->assertFalse($adapter->hasTable('info_foo_bar'));
        $this->assertTrue($adapter->hasTable('statuses_foo_bar'));
        $this->assertTrue($adapter->hasTable('users_foo_bar'));
        $this->assertTrue($adapter->hasTable('user_logins_foo_bar'));
        $this->assertTrue($adapter->hasColumn('users_foo_bar', 'biography'));
        $this->assertTrue($adapter->hasForeignKey('user_logins_foo_bar', ['user_id']));

        // revert all changes to the first
        $this->manager->rollback('production', '20121213232502');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('info'));
        $this->assertFalse($adapter->hasTable('statuses'));
        $this->assertFalse($adapter->hasTable('user_logins'));
        $this->assertFalse($adapter->hasTable('just_logins'));
        $this->assertTrue($adapter->hasColumn('users', 'bio'));
        $this->assertFalse($adapter->hasForeignKey('user_logins', ['user_id']));

        $this->assertFalse($adapter->hasTable('users_baz'));
        $this->assertFalse($adapter->hasTable('info_baz'));
        $this->assertFalse($adapter->hasTable('statuses_baz'));
        $this->assertFalse($adapter->hasTable('user_logins_baz'));

        $this->assertFalse($adapter->hasTable('users_foo_bar'));
        $this->assertFalse($adapter->hasTable('info_foo_bar'));
        $this->assertFalse($adapter->hasTable('statuses_foo_bar'));
        $this->assertFalse($adapter->hasTable('user_logins_foo_bar'));
    }

    public function testBreakpointsTogglingOperateAsExpected()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // Get the versions
        $originalVersions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, reset($originalVersions)['breakpoint']);
        $this->assertEquals(0, end($originalVersions)['breakpoint']);

        // Wait until the second has changed.
        sleep(1);

        // Toggle the breakpoint on most recent migration
        $this->manager->toggleBreakpoint('production', null);

        // ensure breakpoint is set
        $firstToggle = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, reset($firstToggle)['breakpoint']);
        $this->assertEquals(1, end($firstToggle)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column) && $column !== 'breakpoint') {
                    $this->assertEquals($value, $firstToggle[$originalVersionKey][$column]);
                }
            }
        }

        // Wait until the second has changed.
        sleep(1);

        // Toggle the breakpoint on most recent migration
        $this->manager->toggleBreakpoint('production', null);

        // ensure breakpoint is set
        $secondToggle = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, reset($secondToggle)['breakpoint']);
        $this->assertEquals(0, end($secondToggle)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column) && $column !== 'breakpoint') {
                    $this->assertEquals($value, $secondToggle[$originalVersionKey][$column]);
                }
            }
        }

        // Wait until the second has changed.
        sleep(1);

        // Reset all breakpoints and toggle the most recent migration twice
        $this->manager->removeBreakpoints('production');
        $this->manager->toggleBreakpoint('production', null);
        $this->manager->toggleBreakpoint('production', null);

        // ensure breakpoint is not set
        $resetVersions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, reset($resetVersions)['breakpoint']);
        $this->assertEquals(0, end($resetVersions)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column)) {
                    $this->assertEquals($value, $resetVersions[$originalVersionKey][$column]);
                }
            }
        }

        // Wait until the second has changed.
        sleep(1);

        // Set the breakpoint on the latest migration
        $this->manager->setBreakpoint('production', null);

        // ensure breakpoint is set
        $setLastVersions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, reset($setLastVersions)['breakpoint']);
        $this->assertEquals(1, end($setLastVersions)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column) && $column !== 'breakpoint') {
                    $this->assertEquals($value, $setLastVersions[$originalVersionKey][$column]);
                }
            }
        }

        // Wait until the second has changed.
        sleep(1);

        // Set the breakpoint on the first migration
        $this->manager->setBreakpoint('production', reset($originalVersions)['version']);

        // ensure breakpoint is set
        $setFirstVersion = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(1, reset($setFirstVersion)['breakpoint']);
        $this->assertEquals(1, end($setFirstVersion)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column) && $column !== 'breakpoint') {
                    $this->assertEquals($value, $resetVersions[$originalVersionKey][$column]);
                }
            }
        }

        // Wait until the second has changed.
        sleep(1);

        // Unset the breakpoint on the latest migration
        $this->manager->unsetBreakpoint('production', null);

        // ensure breakpoint is set
        $unsetLastVersions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(1, reset($unsetLastVersions)['breakpoint']);
        $this->assertEquals(0, end($unsetLastVersions)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column) && $column !== 'breakpoint') {
                    $this->assertEquals($value, $unsetLastVersions[$originalVersionKey][$column]);
                }
            }
        }

        // Wait until the second has changed.
        sleep(1);

        // Unset the breakpoint on the first migration
        $this->manager->unsetBreakpoint('production', reset($originalVersions)['version']);

        // ensure breakpoint is set
        $unsetFirstVersion = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, reset($unsetFirstVersion)['breakpoint']);
        $this->assertEquals(0, end($unsetFirstVersion)['breakpoint']);

        // ensure no other data has changed.
        foreach ($originalVersions as $originalVersionKey => $originalVersion) {
            foreach ($originalVersion as $column => $value) {
                if (!is_numeric($column)) {
                    $this->assertEquals($value, $unsetFirstVersion[$originalVersionKey][$column]);
                }
            }
        }
    }

    public function testBreakpointWithInvalidVersion()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');
        $this->manager->getOutput()->setDecorated(false);

        // set breakpoint on most recent migration
        $this->manager->toggleBreakpoint('production', 999);

        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        $this->assertStringContainsString('is not a valid version', $output);
    }

    public function testPostgresFullMigration()
    {
        if (!defined('PGSQL_DB_CONFIG')) {
            $this->markTestSkipped('Postgres tests disabled.');
        }

        $configArray = $this->getConfigArray();
        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = [
            $this->getCorrectedPath(__DIR__ . '/_files/postgres'),
        ];
        $configArray['environments']['production'] = PGSQL_DB_CONFIG;
        $config = new Config($configArray);
        $this->manager->setConfig($config);

        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // ensure the database is empty
        $adapter->dropSchema('public');
        $adapter->createSchema('public');
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->migrate('production');

        $this->assertTrue($adapter->hasTable('articles'));
        $this->assertTrue($adapter->hasTable('categories'));
        $this->assertTrue($adapter->hasTable('composite_pks'));
        $this->assertTrue($adapter->hasTable('orders'));
        $this->assertTrue($adapter->hasTable('products'));
        $this->assertTrue($adapter->hasTable('special_pks'));
        $this->assertTrue($adapter->hasTable('special_tags'));
        $this->assertTrue($adapter->hasTable('users'));

        $this->manager->rollback('production', 'all');

        $this->assertFalse($adapter->hasTable('articles'));
        $this->assertFalse($adapter->hasTable('categories'));
        $this->assertFalse($adapter->hasTable('composite_pks'));
        $this->assertFalse($adapter->hasTable('orders'));
        $this->assertFalse($adapter->hasTable('products'));
        $this->assertFalse($adapter->hasTable('special_pks'));
        $this->assertFalse($adapter->hasTable('special_tags'));
        $this->assertFalse($adapter->hasTable('users'));
    }

    public function testMigrationWithDropColumnAndForeignKeyAndIndex()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/drop_column_fk_index_regression');
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        $this->manager->setConfig($config);
        $this->manager->migrate('production', 20190928205056);

        $this->assertTrue($adapter->hasTable('table1'));
        $this->assertTrue($adapter->hasTable('table2'));
        $this->assertTrue($adapter->hasTable('table3'));
        $this->assertTrue($adapter->hasColumn('table1', 'table2_id'));
        $this->assertTrue($adapter->hasForeignKey('table1', ['table2_id'], 'table1_table2_id'));
        $this->assertTrue($adapter->hasIndexByName('table1', 'table1_table2_id'));
        $this->assertTrue($adapter->hasColumn('table1', 'table3_id'));
        $this->assertTrue($adapter->hasForeignKey('table1', ['table3_id'], 'table1_table3_id'));
        $this->assertTrue($adapter->hasIndexByName('table1', 'table1_table3_id'));

        // Run the next migration
        $this->manager->migrate('production');
        $this->assertTrue($adapter->hasTable('table1'));
        $this->assertTrue($adapter->hasTable('table2'));
        $this->assertTrue($adapter->hasTable('table3'));
        $this->assertTrue($adapter->hasColumn('table1', 'table2_id'));
        $this->assertTrue($adapter->hasForeignKey('table1', ['table2_id'], 'table1_table2_id'));
        $this->assertTrue($adapter->hasIndexByName('table1', 'table1_table2_id'));
        $this->assertFalse($adapter->hasColumn('table1', 'table3_id'));
        $this->assertFalse($adapter->hasForeignKey('table1', ['table3_id'], 'table1_table3_id'));
        $this->assertFalse($adapter->hasIndexByName('table1', 'table1_table3_id'));

        // rollback
        $this->manager->rollback('production');
        $this->manager->rollback('production');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('table1'));
        $this->assertTrue($adapter->hasTable('table2'));
        $this->assertTrue($adapter->hasTable('table3'));
        $this->assertFalse($adapter->hasColumn('table1', 'table2_id'));
        $this->assertFalse($adapter->hasForeignKey('table1', ['table2_id'], 'table1_table2_id'));
        $this->assertFalse($adapter->hasIndexByName('table1', 'table1_table2_id'));
        $this->assertFalse($adapter->hasColumn('table1', 'table3_id'));
        $this->assertFalse($adapter->hasForeignKey('table1', ['table3_id'], 'table1_table3_id'));
        $this->assertFalse($adapter->hasIndexByName('table1', 'table1_table3_id'));
    }

    public function testInvalidVersionBreakpoint()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    [
                        '20120111235330' =>
                            [
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ],
                    ]
                ));

        $this->manager->setEnvironments(['mockenv' => $envStub]);
        $this->manager->getOutput()->setDecorated(false);
        $this->manager->setBreakpoint('mockenv', 20120133235330);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertEquals('warning 20120133235330 is not a valid version', trim($outputStr));
    }

    public function testMigrationWillNotBeExecuted()
    {
        if (!defined('MYSQL_DB_CONFIG')) {
            $this->markTestSkipped('Mysql tests disabled.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the should execute migrations
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/should_execute');
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->createDatabase(MYSQL_DB_CONFIG['name']);
        $adapter->disconnect();

        // Run the migration with shouldExecute returning false: the table should not be created
        $this->manager->setConfig($config);
        $this->manager->migrate('production', 20201207205056);

        $this->assertFalse($adapter->hasTable('info'));

        // Run the migration with shouldExecute returning true: the table should be created
        $this->manager->migrate('production', 20201207205057);

        $this->assertTrue($adapter->hasTable('info'));
    }

    public function testMigrationWithCustomColumnTypes()
    {
        $adapter = $this->prepareEnvironment([
            'migrations' => $this->getCorrectedPath(__DIR__ . '/_files/custom_column_types'),
        ]);

        $this->manager->migrate('production');

        $this->assertTrue($adapter->hasTable('users'));

        $columns = array_values($adapter->getColumns('users'));
        $this->assertArrayHasKey(3, $columns);
        $this->assertArrayHasKey(4, $columns);

        $column = $columns[3];
        $this->assertSame('phone_number', $column->getName());
        $this->assertSame('string', $column->getType());
        $this->assertSame(15, $column->getLimit());
        $this->assertTrue($column->getNull());

        $column = $columns[4];
        $this->assertSame('phone_number_ext', $column->getName());
        $this->assertSame('string', $column->getType());
        $this->assertSame(30, $column->getLimit());
        $this->assertFalse($column->getNull());
    }
}
