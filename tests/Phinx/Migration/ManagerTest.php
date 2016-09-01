<?php

namespace Test\Phinx\Migration;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Phinx\Migration\Manager\Environment;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InputInterface $input
     */
    protected $input;

    /**
     * @var OutputInterface $output
     */
    protected $output;

    /**
     * @var Manager
     */
    private $manager;

    protected function setUp()
    {
        $config = new Config($this->getConfigArray());
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        $this->manager = new Manager($config, $this->input, $this->output);
    }

    protected function tearDown()
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
        return array(
            'paths' => array(
                'migrations' => $this->getCorrectedPath(__DIR__ . '/_files/migrations'),
                'seeds' => $this->getCorrectedPath(__DIR__ . '/_files/seeds'),
            ),
            'environments' => array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'production',
                'production' => array(
                    'adapter'   => 'mysql',
                    'host'      => TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
                    'name'      => TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
                    'user'      => TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
                    'pass'      => TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
                    'port'      => TESTS_PHINX_DB_ADAPTER_MYSQL_PORT
                )
            )
        );
    }

    public function testInstantiation()
    {
        $this->assertTrue($this->manager->getOutput() instanceof StreamOutput);
    }

    public function testPrintStatusMethod()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    array (
                        '20120111235330' =>
                            array (
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ),
                        '20120116183504' =>
                            array (
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ),
                    )
                ));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(0, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/up  20120111235330  2012-01-11 23:53:36  2012-01-11 23:53:37  TestMigration/', $outputStr);
        $this->assertRegExp('/up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2/', $outputStr);
    }

    public function testPrintStatusMethodWithBreakpointSet()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    array (
                        '20120111235330' =>
                            array (
                                'version' => '20120111235330',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '1',
                            ),
                        '20120116183504' =>
                            array (
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ),
                    )
                ));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(0, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/BREAKPOINT SET/', $outputStr);
    }

    public function testPrintStatusMethodWithNoMigrations()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));

        // override the migrations directory to an empty one
        $configArray = $this->getConfigArray();
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/nomigrations');
        $config = new Config($configArray);

        $this->manager->setConfig($config);
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(0, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/There are no available migrations. Try creating one using the create command./', $outputStr);
    }

    public function testPrintStatusMethodWithMissingMigrations()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    array (
                        '20120103083300' =>
                            array (
                                'version' => '20120103083300',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ),
                        '20120815145812' =>
                            array (
                                'version' => '20120815145812',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ),
                    )
                ));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(Manager::EXIT_STATUS_MISSING, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING \*\*/', $outputStr);
        $this->assertRegExp('/up  20120815145812  2012-01-16 18:35:40  2012-01-16 18:35:41  Example   *\*\* MISSING \*\*/', $outputStr);
    }

    public function testPrintStatusMethodWithMissingMigrationsAndBreakpointSet()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    array (
                        '20120103083300' =>
                            array (
                                'version' => '20120103083300',
                                'start_time' => '2012-01-11 23:53:36',
                                'end_time' => '2012-01-11 23:53:37',
                                'migration_name' => '',
                                'breakpoint' => '1',
                            ),
                        '20120815145812' =>
                            array (
                                'version' => '20120815145812',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => 'Example',
                                'breakpoint' => '0',
                            ),
                    )
                ));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(Manager::EXIT_STATUS_MISSING, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING \*\*/', $outputStr);
        $this->assertRegExp('/BREAKPOINT SET/', $outputStr);
        $this->assertRegExp('/up  20120815145812  2012-01-16 18:35:40  2012-01-16 18:35:41  Example   *\*\* MISSING \*\*/', $outputStr);
    }

    public function testPrintStatusMethodWithDownMigrations()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(array(
                    '20120111235330'=> array(
                        'version' => '20120111235330',
                        'start_time' => '2012-01-16 18:35:40',
                        'end_time' => '2012-01-16 18:35:41',
                        'migration_name' => '',
                        'breakpoint' => 0
                    ))));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(Manager::EXIT_STATUS_DOWN, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration/', $outputStr);
        $this->assertRegExp('/down  20120116183504                                            TestMigration2/', $outputStr);
    }

    public function testGetMigrationsWithDuplicateMigrationVersions()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Duplicate migration - "' . $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions/20120111235330_duplicate_migration_2.php') . '" has the same version as "20120111235330"'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions'))));
        $manager = new Manager($config, $this->input, $this->output);
        $manager->getMigrations();
    }

    public function testGetMigrationsWithDuplicateMigrationNames()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Migration "20120111235331_duplicate_migration_name.php" has the same name as "20120111235330_duplicate_migration_name.php"'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/duplicatenames'))));
        $manager = new Manager($config, $this->input, $this->output);
        $manager->getMigrations();
    }

    public function testGetMigrationsWithInvalidMigrationClassName()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Could not find class "InvalidClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files/invalidclassname/20120111235330_invalid_class.php') . '"'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/invalidclassname'))));
        $manager = new Manager($config, $this->input, $this->output);
        $manager->getMigrations();
    }

    public function testGetMigrationsWithClassThatDoesntExtendAbstractMigration()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'The class "InvalidSuperClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files/invalidsuperclass/20120111235330_invalid_super_class.php') . '" must extend \Phinx\Migration\AbstractMigration'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/invalidsuperclass'))));
        $manager = new Manager($config, $this->input, $this->output);
        $manager->getMigrations();
    }

    public function testGettingAValidEnvironment()
    {
        $this->assertTrue($this->manager->getEnvironment('production') instanceof Environment);
    }

    /**
     * Test that migrating by date chooses the correct
     * migration to point to.
     *
     * @dataProvider migrateDateDataProvider
     *
     * @param array  $availableMigrations
     * @param string $dateString
     * @param string $expectedMigration
     * @param string $message
     */
    public function testMigrationsByDate(array $availableMigrations, $dateString, $expectedMigration, $message)
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        if (is_null($expectedMigration)) {
            $envStub->expects($this->never())
                    ->method('getVersions');
        } else {
            $envStub->expects($this->once())
                    ->method('getVersions')
                    ->will($this->returnValue($availableMigrations));
        }
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->migrateToDateTime('mockenv', new \DateTime($dateString));
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedMigration)) {
            $this->assertEmpty($output, $message);
        } else {
            $this->assertContains($expectedMigration, $output, $message);
        }
    }

    /**
     * Test that migrating by date chooses the correct migration to point to.
     *
     * @dataProvider rollbackDateDataProvider
     */
    public function testRollbacksByDate(array $availableRollbacks, $dateString, $expectedRollback, $message)
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will($this->returnValue(array_keys($availableRollbacks)));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollbackToDateTime('mockenv', new \DateTime($dateString));
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedRollback)) {
            $this->assertEmpty($output, $message);
        } else {
            $this->assertRegExp($expectedRollback, $output, $message);
        }
    }

    public function testRollbackWithSingleMigrationDoesNotFail()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->any())
                ->method('getVersionLog')
                ->will($this->returnValue([
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                ]));
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will($this->returnValue([20120111235330]));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollback('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertNotContains('Undefined offset: -1', $output);
    }

    public function testRollbackWithTwoMigrationsDoesNotRollbackBothMigrations()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
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
        $this->assertNotContains('== 20120111235330 TestMigration: reverting', $output);
    }

    /**
     * Migration lists, dates, and expected migrations to point to.
     *
     * @return array
     */
    public function migrateDateDataProvider()
    {
        return array(
            array(array('20120111235330', '20120116183504'), '20120118', '20120116183504', 'Failed to migrate all migrations when migrate to date is later than all the migrations'),
            array(array('20120111235330', '20120116183504'), '20120115', '20120111235330', 'Failed to migrate 1 migration when the migrate to date is between 2 migrations'),
            array(array('20120111235330', '20120116183504'), '20120111235330', '20120111235330', 'Failed to migrate 1 migration when the migrate to date is one of the migrations'),
            array(array('20120111235330', '20120116183504'), '20110115', null, 'Failed to migrate 0 migrations when the migrate to date is before all the migrations'),
        );
    }

    /**
     * Migration lists, dates, and expected migrations to point to.
     *
     * @return array
     */
    public function rollbackDateDataProvider()
    {
        return [

            // No breakpoints set

            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20130118',
                null,
                'Failed to rollback 0 migrations when rollback to date is later than all migrations - no breakpoints set',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20120116183504',
                '`No migrations to rollback`',
                'Failed to rollback 0 migrations when rollback to date is the most recent migration - no breakpoints set',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20120115',
                '`20120116183504`',
                'Failed to rollback 1 migration when rollback date is between 2 migrations - no breakpoints set',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20120111235330',
                '`20120116183504`',
                'Failed to rollback 1 migration when rollback datetime is the one of the migrations - no breakpoints set',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20110115',
                '`20120111235330`',
                'Failed to rollback all the migrations when the rollback date is before all the migrations - no breakpoints set',
            ],

            // Breakpoint set on first migration

            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20130118',
                null,
                'Failed to rollback 0 migrations when rollback to date is later than all migrations - breakpoint set on first migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20120116183504',
                '`No migrations to rollback`',
                'Failed to rollback 0 migrations when rollback to date is the most recent migration - breakpoint set on first migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20120115',
                '`20120116183504`',
                'Failed to rollback 1 migration when rollback date is between 2 migrations - breakpoint set on first migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20120111235330',
                '`20120116183504`',
                'Failed to rollback 1 migration when rollback datetime is the one of the migrations - breakpoint set on first migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 0],
                ],
                '20110115',
                '`(?!.*20120111235330.*)20120116183504.*Breakpoint reached.*`s',
                'Failed to rollback 1 migration when the rollback date is before all the migrations and breakpoint set on first migration',
            ],

            // Breakpoint set on last migration

            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20130118',
                null,
                'Failed to rollback 0 migrations when rollback to date is later than all migrations - breakpoint set on last migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20120116183504',
                '`No migrations to rollback`',
                'Failed to rollback 0 migrations when rollback to date is the most recent migration - breakpoint set on last migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20120115',
                '`(?!.*20120116183504.*).*Breakpoint reached.*`s',
                'Failed to rollback 0 migrations when rollback date is between 2 migrations and breakpoint set on last migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20120111235330',
                '`(?!.*20120116183504.*).*Breakpoint reached.*`s',
                'Failed to rollback 0 migrations when rollback datetime is the one of the migrations and breakpoint set on last migration',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 0],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20110115',
                '`(?!.*20120116183504.*).*Breakpoint reached.*`s',
                'Failed to rollback 0 migrations when the rollback date is before all the migrations and breakpoint set on last migration',
            ],

            // Breakpoint set on all migration

            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20130118',
                null,
                'Failed to rollback 0 migrations when rollback to date is later than all migrations - breakpoint set on all migrations',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20120116183504',
                '`No migrations to rollback`',
                'Failed to rollback 0 migrations when rollback to date is the most recent migration - breakpoint set on all migrations',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20120115',
                '`(?!.*20120116183504.*).*Breakpoint reached.*`s',
                'Failed to rollback 0 migrations when rollback date is between 2 migrations and breakpoint set on all migrations',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20120111235330',
                '`(?!.*20120116183504.*).*Breakpoint reached.*`s',
                'Failed to rollback 0 migrations when rollback datetime is the one of the migrations and breakpoint set on all migrations',
            ],
            [
                [
                    '20120111235330' => ['version' => '20120111235330', 'migration' => '', 'breakpoint' => 1],
                    '20120116183504' => ['version' => '20120116183504', 'migration' => '', 'breakpoint' => 1],
                ],
                '20110115',
                '`(?!.*20120116183504.*).*Breakpoint reached.*`s',
                'Failed to rollback 0 migrations when the rollback date is before all the migrations and breakpoint set on all migrations',
            ],
        ];
    }

    public function testExecuteSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->seed('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertContains('GSeeder', $output);
        $this->assertContains('PostSeeder', $output);
        $this->assertContains('UserSeeder', $output);
    }

    public function testExecuteASingleSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->seed('mockenv', 'UserSeeder');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertContains('UserSeeder', $output);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The seed class "NonExistentSeeder" does not exist
     */
    public function testExecuteANonExistentSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->seed('mockenv', 'NonExistentSeeder');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertContains('UserSeeder', $output);
    }

    public function testGettingInputObject()
    {
        $migrations = $this->manager->getMigrations();
        $seeds = $this->manager->getSeeds();
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
        $migrations = $this->manager->getMigrations();
        $seeds = $this->manager->getSeeds();
        $outputObject = $this->manager->getOutput();
        $this->assertInstanceOf('\Symfony\Component\Console\Output\OutputInterface', $outputObject);

        foreach ($migrations as $migration) {
            $this->assertEquals($outputObject, $migration->getOutput());
        }
        foreach ($seeds as $seed) {
            $this->assertEquals($outputObject, $seed->getOutput());
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The environment "invalidenv" does not exist
     */
    public function testGettingAnInvalidEnvironment()
    {
        $this->manager->getEnvironment('invalidenv');
    }

    public function testReversibleMigrationsWorkAsExpected()
    {
        if (!TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED) {
            $this->markTestSkipped('Mysql tests disabled. See TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED constant.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        // override the migrations directory to use the reversible migrations
        $configArray['paths']['migrations'] = $this->getCorrectedPath(__DIR__ . '/_files/reversiblemigrations');
        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $adapter->createDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // ensure up migrations worked
        $this->assertFalse($adapter->hasTable('info'));
        $this->assertTrue($adapter->hasTable('statuses'));
        $this->assertTrue($adapter->hasTable('users'));
        $this->assertTrue($adapter->hasTable('user_logins'));
        $this->assertTrue($adapter->hasColumn('users', 'biography'));
        $this->assertTrue($adapter->hasForeignKey('user_logins', array('user_id')));

        // revert all changes to the first
        $this->manager->rollback('production', '20121213232502');

        // ensure reversed migrations worked
        $this->assertTrue($adapter->hasTable('info'));
        $this->assertFalse($adapter->hasTable('statuses'));
        $this->assertFalse($adapter->hasTable('user_logins'));
        $this->assertTrue($adapter->hasColumn('users', 'bio'));
        $this->assertFalse($adapter->hasForeignKey('user_logins', array('user_id')));
    }

    public function testBreakpointsOperateAsExpected()
    {
        if (!TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED) {
            $this->markTestSkipped('Mysql tests disabled. See TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED constant.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $adapter->createDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');

        // set breakpoint on most recent migration
        $this->manager->toggleBreakpoint('production', null);

        // ensure breakpoint is set
        $versions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(1, end($versions)['breakpoint']);

        // reset all breakpoints
        $this->manager->removeBreakpoints('production');

        // ensure breakpoint is not set
        $versions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, end($versions)['breakpoint']);
    }

    public function testBreakpointWithInvalidVersion()
    {
        if (!TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED) {
            $this->markTestSkipped('Mysql tests disabled. See TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED constant.');
        }
        $configArray = $this->getConfigArray();
        $adapter = $this->manager->getEnvironment('production')->getAdapter();

        $config = new Config($configArray);

        // ensure the database is empty
        $adapter->dropDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $adapter->createDatabase(TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE);
        $adapter->disconnect();

        // migrate to the latest version
        $this->manager->setConfig($config);
        $this->manager->migrate('production');
        $this->manager->getOutput()->setDecorated(false);

        // set breakpoint on most recent migration
        $this->manager->toggleBreakpoint('production', 999);

        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        $this->assertContains('is not a valid version', $output);
    }
}
