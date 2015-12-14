<?php
/**
 * @author t1gor <igor.timoshenkov@gmail.com>
 */
namespace Test\Phinx\Util;

use \Phinx\Util\Util;
use \Phinx\Util\MigrationIterator;

/**
 * Class MigrationIteratorText
 * @package Test\Phinx\Util
 * @group migration-iterator
 */
class MigrationIteratorText extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @covers \Phinx\Util\MigrationIterator::__construct
     */
    public function testConstructInvalidPath()
    {
        $mi = new MigrationIterator('');
    }

    /**
     * @covers \Phinx\Util\MigrationIterator::__construct
     */
    public function testConstruct()
    {
        $mi = new MigrationIterator(__DIR__ . '/_files/migrations');
        $this->assertInstanceOf('\Phinx\Util\MigrationIterator', $mi);
        $this->assertAttributeInstanceOf('\Phinx\Util\MigrationFilterIterator', 'it', $mi);
    }

    /**
     * Check needed files included
     * @covers \Phinx\Util\MigrationIterator::current
     * @covers \Phinx\Util\MigrationIterator::next
     * @covers \Phinx\Util\MigrationIterator::valid
     * @covers \Phinx\Util\MigrationIterator::rewind
     * @covers \Phinx\Util\MigrationIterator::count
     */
    public function testLoop()
    {
        $mi = new MigrationIterator(__DIR__ . '/_files/migrations');
        $this->assertCount(3, $mi);

        foreach ($mi as $migrationFile) {
            $this->assertInternalType('string', $migrationFile);
            $this->assertContains('test_migration', $migrationFile, false);
        }
    }

    /**
     * Check all glob found files are in iterator
     * + plus the files in sub dirs
     */
    public function testBehavesTheSameAsGlob()
    {
        $files = glob(__DIR__ . '/_files/migrations'.DIRECTORY_SEPARATOR.'*.php');
        $mi = new MigrationIterator(__DIR__ . '/_files/migrations');

        $ar = iterator_to_array($mi);
        $onlyValid = array_filter($files, function($filename) {
            return Util::isValidMigrationFileName(basename($filename));
        });

        $diff = array_diff(array_values($ar), $onlyValid);
        $this->assertCount(1, $diff);
        $this->assertContains('sub-folder', array_shift($diff));
    }
}