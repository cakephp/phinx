<?php

namespace Test\Phinx\Migration;

use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\Manager;
use Phinx\Migration\Manager\Environment;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Manager
     */
    private $manager;

    protected function setUp()
    {
        $config = new Config($this->getConfigArray());
        $output = new StreamOutput(fopen('php://memory', 'a', false));
        $this->manager = new Manager($config, $output);
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
                ->method('getVersions')
                ->will($this->returnValue(array('20120111235330', '20120116183504')));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->printStatus('mockenv');

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/up  20120111235330  TestMigration/', $outputStr);
        $this->assertRegExp('/up  20120116183504  TestMigration2/', $outputStr);
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
        $this->manager->printStatus('mockenv');

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/There are no available migrations. Try creating one using the create command./', $outputStr);
    }

    public function testPrintStatusMethodWithMissingMigrations()
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersions')
                ->will($this->returnValue(array('20120103083300', '20120815145812')));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->printStatus('mockenv');

        rewind($this->manager->getOutput()->getStream());
        $outputStr = stream_get_contents($this->manager->getOutput()->getStream());
        $this->assertRegExp('/up  20120103083300  \*\* MISSING \*\*/', $outputStr);
        $this->assertRegExp('/up  20120815145812  \*\* MISSING \*\*/', $outputStr);
    }

    public function testGetMigrationsWithDuplicateMigrationVersions()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Duplicate migration - "' . $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions/20120111235330_duplicate_migration_2.php') . '" has the same version as "20120111235330"'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/duplicateversions'))));
        $output = new StreamOutput(fopen('php://memory', 'a', false));
        $manager = new Manager($config, $output);
        $manager->getMigrations();
    }

    public function testGetMigrationsWithDuplicateMigrationNames()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Migration "20120111235331_duplicate_migration_name.php" has the same name as "20120111235330_duplicate_migration_name.php"'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/duplicatenames'))));
        $output = new StreamOutput(fopen('php://memory', 'a', false));
        $manager = new Manager($config, $output);
        $manager->getMigrations();
    }

    public function testGetMigrationsWithInvalidMigrationClassName()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Could not find class "InvalidClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files/invalidclassname/20120111235330_invalid_class.php') . '"'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/invalidclassname'))));
        $output = new StreamOutput(fopen('php://memory', 'a', false));
        $manager = new Manager($config, $output);
        $manager->getMigrations();
    }

    public function testGetMigrationsWithClassThatDoesntExtendAbstractMigration()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'The class "InvalidSuperClass" in file "' . $this->getCorrectedPath(__DIR__ . '/_files/invalidsuperclass/20120111235330_invalid_super_class.php') . '" must extend \Phinx\Migration\AbstractMigration'
        );
        $config = new Config(array('paths' => array('migrations' => $this->getCorrectedPath(__DIR__ . '/_files/invalidsuperclass'))));
        $output = new StreamOutput(fopen('php://memory', 'a', false));
        $manager = new Manager($config, $output);
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
     */
    public function testMigrationsByDate($availableMigrations, $dateString, $expectedMigration)
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->once())
                ->method('getVersions')
                ->will($this->returnValue($availableMigrations));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->migrateToDateTime('mockenv', new \DateTime($dateString));
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedMigration)) {
            $this->assertEmpty($output);
        } else {
            $this->assertContains($expectedMigration, $output);
        }
    }

    /**
     * Test that migrating by date chooses the correct
     * migration to point to.
     *
     * @dataProvider rollbackDateDataProvider
     */
    public function testRollbacksByDate($availableRollbacks, $dateString, $expectedRollback)
    {
        // stub environment
        $envStub = $this->getMock('\Phinx\Migration\Manager\Environment', array(), array('mockenv', array()));
        $envStub->expects($this->any())
                ->method('getVersions')
                ->will($this->returnValue($availableRollbacks));

        $this->manager->setEnvironments(array('mockenv' => $envStub));
        $this->manager->rollbackToDateTime('mockenv', new \DateTime($dateString));
        rewind($this->manager->getOutput()->getStream());
        $output = stream_get_contents($this->manager->getOutput()->getStream());
        if (is_null($expectedRollback)) {
            $this->assertEmpty($output);
        } else {
            $this->assertContains($expectedRollback, $output);
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
            array(array('20120111235330', '20120116183504'), '20120118', '20120116183504'),
            array(array('20120111235330', '20120116183504'), '20120115', '20120111235330'),
            array(array('20120111235330', '20120116183504'), '20110115', null),
        );
    }

    /**
     * Migration lists, dates, and expected migrations to point to.
     *
     * @return array
     */
    public function rollbackDateDataProvider()
    {
        return array(
            array(array('20120111235330', '20120116183504', '20120120183504'), '20120118', '20120116183504'),
            array(array('20120111235330', '20120116183504'), '20120115', '20120111235330'),
            array(array('20120111235330', '20120116183504'), '20110115', '20120111235330'),
        );
    }

    public function testGettingOutputObject()
    {
        $migrations = $this->manager->getMigrations();
        $outputObject = $this->manager->getOutput();
        $this->assertInstanceOf('\Symfony\Component\Console\Output\OutputInterface', $outputObject);
        foreach ($migrations as $migration) {
            $this->assertEquals($outputObject, $migration->getOutput());
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
}
