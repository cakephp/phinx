<?php
/**
 * Phinx
 *
 * (The MIT license)
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
 * @subpackage Phinx\Db
 */

namespace Test\Phinx\Db;

use Symfony\Component\Console\Output\NullOutput;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Table;
use Phinx\Db\SeedTable;
use Phinx\Db\SqlParser;
use Phinx\Migration\Manager;

class SeedTableTest extends \PHPUnit_Framework_TestCase
{
    public function getAdapters()
    {
        $adapter_list = AdapterFactory::instance()->getAdapters();
        $adapters = array_map(function ($adapter, $alist) {
            $prefix = 'TESTS_PHINX_DB_ADAPTER_' . strtoupper($adapter);
            if (constant($prefix.'_ENABLED')) {
                if ($adapter=='sqlite') {
                    $options = array('name' => constant($prefix.'_DATABASE'));
                } else {
                    $options = array(
                        'host' => constant($prefix.'_HOST'),
                        'name' => constant($prefix.'_DATABASE'),
                        'user' => constant($prefix.'_USERNAME'),
                        'pass' => constant($prefix.'_PASSWORD'),
                        'port' => constant($prefix.'_PORT')
                    );
                }
                if ($adapter == 'pgsql') {
                    $options['schema'] = constant($prefix.'_DATABASE_SCHEMA');
                }

                $class = $alist[$adapter];
                $a = new $class($options, new NullOutput());

                if (isset($options['schema'])) {
                    $a->dropAllSchemas();
                    $a->createSchema($options['schema']);
                } else {
                    $a->dropDatabase($options['name']);
                    $a->createDatabase($options['name']);
                }
                $a->disconnect();
                return array($a);
            }
        }, array_keys($adapter_list), array_pad(array(), count($adapter_list), $adapter_list));
        return array_filter($adapters, function ($x) { return $x!==null; });
    }

    /**
     * @dataProvider getAdapters
     */
    public function testSeedTableInsertSql($adapter)
    {
        foreach (array('test_seed', 'test_seed2') as $t) {
            if ($adapter->hasTable($t)) {
                $adapter->dropTable($t);
            }
        }
        // create a test seed table for SeedTable tests
        $adapter->query("create table test_seed(i int, v varchar(10))");
        $adapter->query("insert into test_seed select 1 i,'fizzie' v union select 2,'figgle''s' union select 3, 'fu\nk' union select 4, null");
        $seed = new SeedTable('test_seed', array(), $adapter);
        $this->assertNull($seed->getWhere());
        $insert = $seed->getInsertSql();

        // each adapter quotes the insert statement differently
        $expected = SeedTableTest::quoteExpectedSql($adapter, 'test_seed',
            "INSERT INTO %table_name (%i, %v) VALUES ('1', 'fizzie');
INSERT INTO %table_name (%i, %v) VALUES ('2', " . $adapter->quote('figgle\'s') . ");
INSERT INTO %table_name (%i, %v) VALUES ('3', " . $adapter->quote("fu\nk") . ");
INSERT INTO %table_name (%i, %v) VALUES ('4', null)");
    
        // we can't guarantee ordering so just make sure each insert statement exists
        foreach (explode(";\n", $expected) as $x) {
            $this->assertTrue(strpos($insert, $x)!==false, "insert should contain: $x");
        }
        // test insert actually works. This will throw exception if not
        $adapter->query("create table test_seed2 as select * from test_seed limit 0");
        foreach (SqlParser::parse($insert) as $sql) {
            $adapter->query(str_replace('test_seed', 'test_seed2', $sql));
        }
        $rows = $adapter->fetchAll("select * from test_seed2");
        $this->assertCount(
            4,
            $rows
        );
        // make sure null record was put in properly
        $rows = $adapter->fetchAll("select * from test_seed2 where v is null");
        $this->assertCount(1, $rows, "null should be properly inserted");

        $rows = [];
        $rows = $adapter->fetchAll("select v from test_seed2 where v like '%\n%'");
        $this->assertCount(1, $rows, "new line should be properly inserted");
    }

    /**
     * @dataProvider getAdapters
     */
    public function testSeedTableWhere($adapter)
    {
        foreach (array('test_seed', 'test_seed2') as $t) {
            if ($adapter->hasTable($t)) {
                $adapter->dropTable($t);
            }
        }
        $adapter->query("create table test_seed(i int, v varchar(10))");
        $adapter->query("insert into test_seed select 1 i,'fizzie' v union select 2,'figgle' union select 3, 'funk'");
        $seed = new SeedTable(array('name'=>'test_seed', 'where'=>'i>2'), array(), $adapter);

        $this->assertTrue($seed->exists(), 'seed->exists() should be true for existing table');
        $this->assertEquals('i>2', $seed->getWhere(), 'where clause should be set');

        $insert = $seed->getInsertSql();
        $this->assertEquals(
            SeedTableTest::quoteExpectedSql($adapter, 'test_seed', "INSERT INTO %table_name (%i, %v) VALUES ('3', 'funk');\n"),
            $insert
        );
        // test insert actually works. This will throw exception if not
        $adapter->query("create table test_seed2 as select * from test_seed limit 0");
        $adapter->query(str_replace('test_seed', 'test_seed2', $insert));
        $rows = $adapter->fetchAll("select * from test_seed2");
        $this->assertCount(
            1,
            $rows
        );
    }

    /**
     * @dataProvider getAdapters
     */
    public function testNonExistentTable($adapter)
    {
        $seed = new SeedTable('ThisTableDoesNotExist', array(), $adapter);
        $this->assertEmpty($seed->getInsertSql(), 'non-existent table should return false');
        $this->assertFalse($seed->exists(), 'seed->exists() should be false for non existent table');
    }

    /**
     * @dataProvider getAdapters
     */
    public function testEmptySeed($adapter)
    {
        if ($adapter->hasTable('test_empty_seed')) {
            $adapter->dropTable('test_empty_seed');
        }
        $adapter->query("create table test_empty_seed(i int);");
        $seed = new SeedTable('test_empty_seed', array(), $adapter);
        $this->assertEquals('', $seed->getInsertSql(), 'empty tables should return empty string');
        $adapter->dropTable('test_empty_seed');
    }

    /**
     * @dataProvider getAdapters
     * @expectedException \RuntimeException
     */
    public function testSeedDataWithoutAdapter($adapter)
    {
        $seed = new SeedTable('test_seed');
        $seed->getInsertSql();
    }

    /**
     * @dataProvider getAdapters
     * @expectedException \RuntimeException
     */
    public function testSeedExistsWithoutAdapter($adapter)
    {
        $seed = new SeedTable('test_seed');
        $seed->exists();
    }

    /**
     * make sure seed dependencies work as expected and that
     * circular dependencies are not possible
     */
    public function testSeedDependencies()
    {
        $a = new SeedTable('a');
        $b = new SeedTable('b');
        $c = new SeedTable('c');
        $d = new SeedTable('d');

        $a->setDependency($b);
        $b->setDependency($c);
        $b->setDependency($d);

        $this->assertTrue($a->dependsOn($b));
        $this->assertFalse($a->dependsOn($c));
        $this->assertTrue($a->dependsOn($c, true));
        $this->assertTrue($a->dependsOn($d, true));

        $ret = $b->setDependency($a);
        $this->assertFalse($ret);
        $this->assertFalse($b->dependsOn($a));
    }

    private static function quoteExpectedSql($adapter, $table_name, $expected)
    {
        return str_replace(
            array('%table_name', '%i', '%v'),
            array( $adapter->quoteTableName($table_name), $adapter->quoteColumnName('i'), $adapter->quoteColumnName('v')),
            $expected);
    }
}
