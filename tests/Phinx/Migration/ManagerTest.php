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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();

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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertRegExp('/\s*up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING \*\*'.PHP_EOL.
            '\s*up  20120815145812  2012-01-16 18:35:40  2012-01-16 18:35:41  Example   *\*\* MISSING \*\*'.PHP_EOL. 
            '\s*down  20120111235330                                            TestMigration'.PHP_EOL.
            '\s*down  20120116183504                                            TestMigration2/', $outputStr);
    }
    
    public function testPrintStatusMethodWithMissingLastMigration()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(
                    array (
                        '20120111235330' => 
                            array (
                                'version' => '20120111235330',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => 0
                            ),
                        '20120116183504' =>
                            array (
                                'version' => '20120116183504',
                                'start_time' => '2012-01-16 18:35:40',
                                'end_time' => '2012-01-16 18:35:41',
                                'migration_name' => '',
                                'breakpoint' => '0',
                            ),
                        '20120120145114' =>
                            array (
                                'version' => '20120120145114',
                                'start_time' => '2012-01-20 14:51:14',
                                'end_time' => '2012-01-20 14:51:14',
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

        // note that the order is important: missing migrations should appear before down migrations
        $this->assertRegExp('/\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration'.PHP_EOL.
            '\s*up  20120116183504  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration2'.PHP_EOL.
            '\s*up  20120120145114  2012-01-20 14:51:14  2012-01-20 14:51:14  Example   *\*\* MISSING \*\*/', $outputStr);
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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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
    
    public function testPrintStatusMethodWithMissingAndDownMigrations()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(array(
                    '20120111235330' => 
                        array (
                            'version' => '20120111235330',
                            'start_time' => '2012-01-16 18:35:40',
                            'end_time' => '2012-01-16 18:35:41',
                            'migration_name' => '',
                            'breakpoint' => 0
                        ),
                    '20120103083300' =>
                        array (
                            'version' => '20120103083300',
                            'start_time' => '2012-01-11 23:53:36',
                            'end_time' => '2012-01-11 23:53:37',
                            'migration_name' => '',
                            'breakpoint' => 0,
                        ),
                    '20120815145812' =>
                        array (
                            'version' => '20120815145812',
                            'start_time' => '2012-01-16 18:35:40',
                            'end_time' => '2012-01-16 18:35:41',
                            'migration_name' => 'Example',
                            'breakpoint' => 0,
                        ))));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->getOutput()->setDecorated(false);
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(Manager::EXIT_STATUS_MISSING, $return);

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        
        // note that the order is important: missing migrations should appear before down migrations (and in the right 
        // place with regard to other up non-missing migrations)
        $this->assertRegExp('/\s*up  20120103083300  2012-01-11 23:53:36  2012-01-11 23:53:37  *\*\* MISSING \*\*'.PHP_EOL.
            '\s*up  20120111235330  2012-01-16 18:35:40  2012-01-16 18:35:41  TestMigration'.PHP_EOL. 
            '\s*down  20120116183504                                            TestMigration2/', $outputStr);
    }
    
    /**
     * Test that ensures the status header is correctly printed with regards to the version order
     *
     * @dataProvider statusVersionOrderProvider
     *
     * @param array  $config
     * @param string $expectedStatusHeader
     */
    public function testPrintStatusMethodVersionOrderHeader($config, $expectedStatusHeader)
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();
        $envStub->expects($this->once())
                ->method('getVersionLog')
                ->will($this->returnValue(array()));

        $output = new RawBufferedOutput();
        $this->manager = new Manager($config, $this->input, $output);

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $return = $this->manager->printStatus('mockenv');
        $this->assertEquals(Manager::EXIT_STATUS_DOWN, $return);

        $outputStr = $this->manager->getOutput()->fetch();
        $this->assertContains($expectedStatusHeader, $outputStr);
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
                ' Status  <info>[Migration ID]</info>  Started              Finished             Migration Name '
            ],
            'With the creation version order' => [
                $configWithCreationVersionOrder,
                ' Status  <info>[Migration ID]</info>  Started              Finished             Migration Name '
            ],
            'With the execution version order' => [
                $configWithExecutionVersionOrder,
                ' Status  Migration ID    <info>[Started          ]</info>  Finished             Migration Name '
            ]
        ];
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Invalid version_order configuration option
     */
    public function testPrintStatusInvalidVersionOrderKO()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();

        $configArray = $this->getConfigArray();
        $configArray['version_order'] ='invalid';
        $config = new Config($configArray);

        $this->manager = new Manager($config, $this->input, $this->output);

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->printStatus('mockenv');
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

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollback('mockenv', $version);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals("No migrations to rollback".PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }
            
            foreach ($expectedOutput as $expectedLine) {
                $this->assertContains($expectedLine, $output);
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
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollback('mockenv', $version, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals("No migrations to rollback".PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }
            
            foreach ($expectedOutput as $expectedLine) {
                $this->assertContains($expectedLine, $output);
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
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $configArray = $this->getConfigArray();
        $configArray['version_order'] = \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME;
        $config = new Config($configArray);
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        
        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(array('mockenv' => $envStub));
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
                $this->assertContains($expectedLine, $output);
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
            ->setConstructorArgs(array('mockenv', array()))
            ->getMock();
        $envStub->expects($this->any())
            ->method('getVersionLog')
            ->will($this->returnValue($availableRollbacks));

        // get a manager with a config whose version order is set to execution time
        $configArray = $this->getConfigArray();
        $configArray['version_order'] = \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME;
        $config = new Config($configArray);
        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->output->setDecorated(false);
        
        $this->manager = new Manager($config, $this->input, $this->output);
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollback('mockenv', $date, false, false);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());

        if (is_null($expectedOutput)) {
            $this->assertEquals("No migrations to rollback".PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }
            
            foreach ($expectedOutput as $expectedLine) {
                $this->assertContains($expectedLine, $output);
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

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollback('mockenv');
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertContains('== 20120111235330 TestMigration: reverting', $output);
        $this->assertContains('== 20120111235330 TestMigration: reverted', $output);
        $this->assertNotContains('No migrations to rollback', $output);
        $this->assertNotContains('Undefined offset: -1', $output);
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
        $this->assertNotContains('== 20120111235330 TestMigration: reverting', $output);
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
            ->setConstructorArgs(array('mockenv', array()))
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
        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollback('mockenv', null);
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedOutput)) {
            $this->assertEquals("No migrations to rollback".PHP_EOL, $output);
        } else {
            if (is_string($expectedOutput)) {
                $expectedOutput = [$expectedOutput];
            }
            
            foreach ($expectedOutput as $expectedLine) {
                $this->assertContains($expectedLine, $output);
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
        return array(
            array(array('20120111235330', '20120116183504'), '20120118', '20120116183504', 'Failed to migrate all migrations when migrate to date is later than all the migrations'),
            array(array('20120111235330', '20120116183504'), '20120115', '20120111235330', 'Failed to migrate 1 migration when the migrate to date is between 2 migrations'),
            array(array('20120111235330', '20120116183504'), '20120111235330', '20120111235330', 'Failed to migrate 1 migration when the migrate to date is one of the migrations'),
            array(array('20120111235330', '20120116183504'), '20110115', null, 'Failed to migrate 0 migrations when the migrate to date is before all the migrations'),
        );
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
                    null
                ],
            'Rollback to date of the most recent migration - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback to date between 2 migrations - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120115', 
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to date of the oldest migration - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330', 
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to date before all the migrations - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115', 
                    ['== 20120116183504 TestMigration2: reverted', '== 20120111235330 TestMigration: reverted']
                ],

            // Breakpoint set on first migration

            'Rollback to date which is later than all migrations - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20130118000000', 
                    null
                ],
            'Rollback to date of the most recent migration - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback to date between 2 migrations - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120115', 
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to date of the oldest migration - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120111235330', 
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to date before all the migrations - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20110115', 
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],

            // Breakpoint set on last migration

            'Rollback to date which is later than all migrations - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20130118000000', 
                    null
                ],
            'Rollback to date of the most recent migration - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback to date between 2 migrations - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120115000000', 
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to date of the oldest migration - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120111235330', 
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to date before all the migrations - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000', 
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            
            // Breakpoint set on all migrations

            'Rollback to date which is later than all migrations - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20130118000000', 
                    null
                ],
            'Rollback to date of the most recent migration - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback to date between 2 migrations - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120115000000', 
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to date of the oldest migration - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120111235330', 
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to date before all the migrations - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20110115000000', 
                    'Breakpoint reached. Further rollbacks inhibited.'
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
        return array(

            // No breakpoints set

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - no breakpoints set' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20131212000000',
                    null
                ),
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - no breakpoints set' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20111212000000',
                    ['== 20120111235330 TestMigration: reverted', '== 20120116183504 TestMigration2: reverted']
                ),
            'Rollback to start time of first created version which was the last to be executed - no breakpoints set' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20120120235330',
                    null
                ),
            'Rollback to start time of second created version which was the first to be executed - no breakpoints set' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20120117183504',
                    '== 20120111235330 TestMigration: reverted'
                ),
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - no breakpoints set' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20120118000000',
                    '== 20120111235330 TestMigration: reverted'
                ),
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - no breakpoints set' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    null,
                    '== 20120111235330 TestMigration: reverted'
                ),


            // Breakpoint set on first/last created/executed migration

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20131212000000',
                    null
                ),
            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20131212000000',
                    null
                ),
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20111212000000',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20111212000000',
                    ['== 20120111235330 TestMigration: reverted', 'Breakpoint reached. Further rollbacks inhibited.']
                ),
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on first created (and last executed) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20120120235330',
                    null
                ),
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on first executed (and last created) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20120120235330',
                    null
                ),
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on first created (and last executed) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20120117183504',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on first executed (and last created) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20120117183504',
                    '== 20120111235330 TestMigration: reverted'
                ),
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20120118000000',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    '20120118000000',
                    '== 20120111235330 TestMigration: reverted'
                ),
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on first created (and last executed) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on first executed (and last created) migration' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ),
                    null,
                    '== 20120111235330 TestMigration: reverted'
                ),

            // Breakpoint set on all migration

            'Rollback to date later than all migration start times when they were created in a different order than they were executed - breakpoints set on all migrations' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20131212000000',
                    null
                ),
            'Rollback to date earlier than all migration start times when they were created in a different order than they were executed - breakpoints set on all migrations' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20111212000000',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback to start time of first created version which was the last to be executed - breakpoints set on all migrations' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20120120235330',
                    null
                ),
            'Rollback to start time of second created version which was the first to be executed - breakpoints set on all migrations' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20120117183504',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback to date between the 2 migrations when they were created in a different order than they were executed - breakpoints set on all migrations' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    '20120118000000',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
            'Rollback the last executed migration when the migrations were created in a different order than they were executed - breakpoints set on all migrations' => 
                array(
                    array(
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ),
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ),
        );
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
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to the latest version - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    ['== 20120111235330 TestMigration: reverted', '== 20120116183504 TestMigration2: reverted']
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
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to the latest version - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on first migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 0],
                    ],
                    '0',
                    '== 20120116183504 TestMigration2: reverted'
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
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to the latest version - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback last version - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null, 
                    'Breakpoint reached. Further rollbacks inhibited.'
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
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to the latest version - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '20120116183504', 
                    null
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    '0',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback last version - breakpoint set on last migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'migration_name' => '', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'migration_name' => '', 'breakpoint' => 1],
                    ],
                    null, 
                    'Breakpoint reached. Further rollbacks inhibited.'
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
                ]
        ];
    }

    public function rollbackToVersionByExecutionTimeDataProvider()
    {
        return [

            // No breakpoints set

            'Rollback to first created version with was also the first to be executed - no breakpoints set' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to last created version which was also the last to be executed - no breakpoints set' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    '20120116183504',
                    'No migrations to rollback'
                ],
            'Rollback all versions (ie. rollback to version 0) - no breakpoints set' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    '0',
                    ['== 20120111235330 TestMigration: reverted', '== 20120116183504 TestMigration2: reverted']
                ],
            'Rollback to second created version which was the first to be executed - no breakpoints set' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                    ],
                    '20120116183504',
                    '== 20120111235330 TestMigration: reverted'
                ],
            'Rollback to first created version which was the second to be executed - no breakpoints set' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20120111235330',
                    'No migrations to rollback'
                ],
            'Rollback last executed version which was also the last created version - no breakpoints set' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    null,
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback last executed version which was the first created version - no breakpoints set' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted'
                ],
            'Rollback to non-existing version - no breakpoints set' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - no breakpoints set' => 
                [
                    [
                        '20111225000000' => array('version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],

            // Breakpoint set on first migration

            'Rollback to first created version with was also the first to be executed - breakpoint set on first (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    '20120111235330',
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on first (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    '20120116183504',
                    'No migrations to rollback'
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on first (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    '0',
                    ['== 20120116183504 TestMigration2: reverted', 'Breakpoint reached. Further rollbacks inhibited.']
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on first executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                    ],
                    '20120116183504',
                    '== 20120111235330 TestMigration: reverted'
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on first created migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                    ],
                    '20120116183504',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on first executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20120111235330',
                    'No migrations to rollback'
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on first created migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20120111235330',
                    'No migrations to rollback'
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on first (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                    ],
                    null,
                    '== 20120116183504 TestMigration2: reverted'
                ],
            'Rollback last executed version which was the first created version - breakpoint set on first executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted'
                ],
            'Rollback last executed version which was the first created version - breakpoint set on first created migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to non-existing version - breakpoint set on first executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on first executed migration' => 
                [
                    [
                        '20111225000000' => array('version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],

            // Breakpoint set on last migration

            'Rollback to first created version with was also the first to be executed - breakpoint set on last (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on last (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    '20120116183504',
                    'No migrations to rollback'
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on last (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    '0',
                    ['Breakpoint reached. Further rollbacks inhibited.']
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on last executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                    ],
                    '20120116183504',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on last created migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                    ],
                    '20120116183504',
                    '== 20120111235330 TestMigration: reverted'
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on last executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20120111235330',
                    'No migrations to rollback'
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on last created migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    '20120111235330',
                    'No migrations to rollback'
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on last (executed and created) migration' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback last executed version which was the first created version - breakpoint set on last executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback last executed version which was the first created version - breakpoint set on last created migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 0),
                    ],
                    null,
                    '== 20120111235330 TestMigration: reverted'
                ],
            'Rollback to non-existing version - breakpoint set on last executed migration' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on last executed migration' => 
                [
                    [
                        '20111225000000' => array('version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 0),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 0),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],

            // Breakpoint set on all migrations

            'Rollback to first created version with was also the first to be executed - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    '20120111235330',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to last created version which was also the last to be executed - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    '20120116183504',
                    'No migrations to rollback'
                ],
            'Rollback all versions (ie. rollback to version 0) - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    '0',
                    ['Breakpoint reached. Further rollbacks inhibited.']
                ],
            'Rollback to second created version which was the first to be executed - breakpoint set on all migrations' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'end_time' => '2012-01-10 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                    ],
                    '20120116183504',
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to first created version which was the second to be executed - breakpoint set on all migrations' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20120111235330',
                    'No migrations to rollback'
                ],
            'Rollback last executed version which was also the last created version - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'end_time' => '2012-01-12 23:53:30', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback last executed version which was the first created version - breakpoint set on all migrations' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    null,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            'Rollback to non-existing version - breakpoint set on all migrations' => 
                [
                    [
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
                ],
            'Rollback to missing version - breakpoint set on all migrations' => 
                [
                    [
                        '20111225000000' => array('version' => '20111225000000', 'start_time' => '2011-12-25 00:00:00', 'end_time' => '2011-12-25 00:00:00', 'breakpoint' => 1),
                        '20120116183504' => array('version' => '20120116183504', 'start_time' => '2012-01-17 18:35:04', 'end_time' => '2012-01-17 18:35:04', 'breakpoint' => 1),
                        '20120111235330' => array('version' => '20120111235330', 'start_time' => '2012-01-20 23:53:30', 'end_time' => '2012-01-20 23:53:30', 'breakpoint' => 1),
                    ],
                    '20121225000000', 
                    'Target version (20121225000000) not found',
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
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted'
                ],
            
            'Rollback to last migration with execution time version ordering - no breakpoints set' => 
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted'
                ],
            
            'Rollback to last migration with missing last migration and creation time version ordering - no breakpoints set' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted'
                ],
            
            'Rollback to last migration with missing last migration and execution time version ordering - no breakpoints set' => 
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted'
                ],
            
            // Breakpoint set on last migration

            'Rollback to last migration with creation time version ordering - breakpoint set on last created migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            
            'Rollback to last migration with creation time version ordering - breakpoint set on last executed migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted'
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on last non-missing created migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            
            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on last non-missing executed migration' => 
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 0],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            
            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on missing migration' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    '== 20120116183504 TestMigration2: reverted'
                ],
            
            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on missing migration' => 
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 0],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 0],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME,
                    '== 20120111235330 TestMigration: reverted'
                ],
            
            // Breakpoint set on all migrations

            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            
            'Rollback to last migration with creation time version ordering - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],

            'Rollback to last migration with missing last migration and creation time version ordering - breakpoint set on all migrations' => 
                [
                    [
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-16 18:35:04', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            
            'Rollback to last migration with missing last migration and execution time version ordering - breakpoint set on all migrations' => 
                [
                    [
                        '20120116183504' => ['version' => '20120116183504', 'start_time' => '2012-01-10 18:35:04', 'breakpoint' => 1],
                        '20120111235330' => ['version' => '20120111235330', 'start_time' => '2012-01-12 23:53:30', 'breakpoint' => 1],
                        '20130101225232' => ['version' => '20130101225232', 'start_time' => '2013-01-01 22:52:32', 'breakpoint' => 1],
                    ],
                    \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME,
                    'Breakpoint reached. Further rollbacks inhibited.'
                ],
            ];
    }

    public function testExecuteSeedWorksAsExpected()
    {
        // stub environment
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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
        $envStub = $this->getMockBuilder('\Phinx\Migration\Manager\Environment')
            ->setConstructorArgs(['mockenv', []])
            ->getMock();
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

        $versions = $this->manager->getEnvironment('production')->getVersionLog();
        $lastVersion = end($versions);

        // Wait until the second has changed.
        $now = time();
        while($now == time());

        // set breakpoint on most recent migration
        $this->manager->toggleBreakpoint('production', null);

        // ensure breakpoint is set
        $versions = $this->manager->getEnvironment('production')->getVersionLog();
        $currentVersion = end($versions);
        $this->assertEquals(1, $currentVersion['breakpoint']);

        // ensure no other data has changed.
        foreach($lastVersion as $key => $value) {
            if (!is_numeric($key) && $key != 'breakpoint') {
                $this->assertEquals($value, $currentVersion[$key]);
            }
        }

        // Wait until the second has changed.
        $now = time();
        while($now == time());

        // reset all breakpoints
        $this->manager->removeBreakpoints('production');

        // ensure breakpoint is not set
        $versions = $this->manager->getEnvironment('production')->getVersionLog();
        $this->assertEquals(0, end($versions)['breakpoint']);

        // ensure no other data has changed.
        foreach($lastVersion as $key => $value) {
            if (!is_numeric($key) && $key != 'breakpoint') {
                $this->assertEquals($value, $currentVersion[$key]);
            }
        }
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

/**
 * RawBufferedOutput is a specialized BufferedOutput that outputs raw "writeln" calls (ie. it doesn't replace the
 * tags like <info>message</info>.
 */
class RawBufferedOutput extends \Symfony\Component\Console\Output\BufferedOutput
{
    public function writeln($messages, $options = self::OUTPUT_RAW)
    {
        $this->write($messages, true, $options);
    }
}
