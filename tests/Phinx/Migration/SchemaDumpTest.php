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

namespace Test\Phinx\SchemaDumpTest;

use Symfony\Component\Console\Output\NullOutput;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\SqlParser;
use Phinx\Migration\Manager;

class SchemaDumpTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Manager[]
     */
    private static $managers=array();

    /**
     * @var string[]
     */
    private static $dump_files=array();

    /**
     * @var string[]
     * cache the dumped schema for each manager
     */
    private static $schema_dumps=array();

    /**
     * @var string[][]
     * raw $schema_dumps split out into sql commands
     * for a given adapter
     */
    private static $schema_statements=array();

    /**
     * @var string[][] 
     *
     * create statements for a given adapter
     * insert        "
     * foreign key   "
     * primary key   "
     */
    private static $sql_info=array();

    /**
     * @var string
     *
     * set up script that creates a test schema. schema consists of
     * 3 tables, 2 of which are seed tables
     */
    private static $setup_script = <<<SQL
create table test_seed(id int not null primary key, ext_id int);
insert into test_seed select 1, 1 union select 2,2;
create table test_seed2(id int not null primary key,  v varchar(10));
insert into test_seed2 select 1, 'fuzzie' union select 2, 'wuzzie';
alter table test_seed add constraint test_fk foreign key ( ext_id ) references test_seed2 ( id ) on delete cascade on update cascade;
create table foo_users(user_id int not null primary key, name varchar(10), constraint foo_u unique (user_id,name));
create index foo_nu on foo_users(name);
insert into foo_users select 1, 'jimmy';
SQL;

    public static function setUpBeforeClass()
    {
        $adapters = array_keys(AdapterFactory::instance()->getAdapters());
        foreach( $adapters as $adapter ) {

            $prefix = 'TESTS_PHINX_DB_ADAPTER_' . strtoupper($adapter);
            if( !@constant("${prefix}_ENABLED") ) 
                continue;

            $conf = array(
                'paths' => array(
                    'schema' => '/tmp/idontexist'),
                'seeds' => array(
                    'tables' => array(
                        array('name'=>'test_seed', 'where'=>'id>=0'),
                        'test_seed2')
                    ),
                'environments' => array(
                    'default_migration_table' => 'testing_seed_log',
                    'default_environment' => 'phpunit',
                    'phpunit' => array(
                        'adapter' => $adapter,
                        'name' => constant("${prefix}_DATABASE")
                    )
                ));
            if($adapter != 'sqlite') {
                $conf['environments']['phpunit'] = array(
                    'adapter' => $adapter,
                    'host' => constant("${prefix}_HOST"),
                    'name' => constant("${prefix}_DATABASE"),
                    'user' => constant("${prefix}_USERNAME"),
                    'pass' => constant("${prefix}_PASSWORD"),
                    'port' => constant("${prefix}_PORT")
                );
                if ( $adapter == 'pgsql' )
                    $conf['environments']['phpunit']['schema'] = constant("${prefix}_DATABASE_SCHEMA");
            }

            $manager = new Manager(new Config($conf), new NullOutput());
            $adapterObj = $manager->getEnvironment('phpunit')->getAdapter();

            // kill the schema for this test
            if($adapter=='pgsql') {
                $adapterObj->dropAllSchemas();
                $adapterObj->createSchema($conf['environments']['phpunit']['schema']);
            } else {
                $adapterObj->dropDatabase($conf['environments']['phpunit']['name']);
                if($adapter=='mysql')
                    $adapterObj->query('set storage_engine=InnoDB');
                $adapterObj->createDatabase($conf['environments']['phpunit']['name']);
            }
            $adapterObj->disconnect();
            if($adapter=='mysql')
                $adapterObj->query('set storage_engine=InnoDB');
            $adapterObj->createSchemaTable();

            // setup the schema
            foreach(SqlParser::parse(static::$setup_script) as $cmd) { 
                $adapterObj->query($cmd);
            }
            
            $time_sql = <<<SQL
insert into testing_seed_log 
select 1, ?, ? 
 union 
select 123456, ?, ?
SQL;
            if( $adapter == 'pgsql' ) {
                $time_sql = str_replace('?','cast(? as timestamp)', $time_sql);
            }
            $st = $adapterObj->getConnection()->prepare($time_sql);
            $st->execute(array('2014-01-01 00:00:01','2014-01-01 00:00:02',
                '2015-01-01 00:01:00','2015-01-01 00:05:00'));
            
            static::$managers[$adapter] = $manager;
            # create a temp directory for the dump to live
            static::$dump_files[$adapter] = tempnam( sys_get_temp_dir(), "phinxDump_$adapter" );
        }

    }

    public static function tearDownAfterClass()
    {
        foreach(static::$dump_files as $file) {
            unlink($file);
        }
    }

    /**
     * dataProvider function for test cases
     */
    public static function getAdapters()
    {
        $adapters = array_keys(AdapterFactory::instance()->getAdapters());
        $adaptersUnderTest = array();
        foreach( $adapters as $adapter ) {
            $prefix = 'TESTS_PHINX_DB_ADAPTER_' . strtoupper($adapter);
            if( !@constant("${prefix}_ENABLED") ) 
                continue;
            $adaptersUnderTest[] = array($adapter);
        }
        return $adaptersUnderTest;
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testSchemaCorrectTables($adapter)
    {
        $creates = $this->sql('creates', $adapter);

        $this->assertTrue(static::$sql_info[$adapter]['dumped_migration_structure'], 'schema dump should include migration log table structure');

        // make sure that 4 table statements are present. 
        // the three defined tables plus the schema log table
        $this->assertCount(4, $creates);

        // make sure the schema table is listed explicitly
        $env = static::$managers[$adapter]->getConfig()->getEnvironment('phpunit');
        $schema_table = $env['default_migration_table'];
        $this->assertTrue(strlen($schema_table)>0);

        foreach( $creates as $sql ) {
            $this->assertNotRegExp( '/FOREIGN KEY/mi', $sql, 'FOREIGN KEY statements should be removed from CREATE TABLE statements');
        }
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testSchemaCorrectSeeds($adapter)
    {
        $inserts = $this->sql('inserts', $adapter);

        $this->assertTrue(static::$sql_info[$adapter]['dumped_migration_data'], 'schema dump should include migration log table data');

        // 2 record from test_seed, 2 from test_seed2
        // plus 2 for phinx migration log
        // t1,t2,t3 are in the seed config but do not exist
        $this->assertCount(6, $inserts);

        foreach($inserts as $insert) {
            $this->assertRegExp('/\b(test_seed\d?|testing_seed_log)\b/', $insert,'should only dump the seed tables specified + migration_table');
        }
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testPrimaryKeys($adapter)
    {
        $pks = $this->sql('primaryKeys', $adapter);
        $this->assertCount(3, $pks, 'adapters should include primary key data in table creation sql');
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testForeignKeysAfterCreate($adapter)
    {
        if($adapter=='sqlite')
            $this->markTestSkipped('sqlite adapter doesn\'t support foreign keys right now');
        $this->getSqlInfo($adapter);
        $this->assertTrue(static::$sql_info[$adapter]['min_fk_line'] > static::$sql_info[$adapter]['max_create_line'], 'foreign key definitions should happen after table creates');
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testForeignKeyActions($adapter)
    {
        if($adapter=='sqlite')
            $this->markTestSkipped('sqlite adapter doesn\'t support foreign keys right now');
        
        $fks = $this->sql('foreignKeys', $adapter);
        $this->assertCount(1, $fks);

        foreach($fks as $fk) {
            $this->assertRegExp('/ON DELETE CASCADE/mi', $fk, 'onDelete action should be returned in foreign key create statement');
            $this->assertRegExp('/ON UPDATE CASCADE/mi', $fk, 'onUpdate action should be returned in foreign key create statement');
        }
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testNormalIndexes($adapter)
    {
        // make sure the foo_nu index was created
        $keys = $this->sql('normalKeys', $adapter);
        $this->assertCount(1, $keys, 'test schema should have found one non-unique named foo_nu keys');
        $this->assertRegExp('/\(`?name`?\)/m', $keys[0], 'non unique key `foo_nu` on column `name` should exist');
    }

    /**
     * @dataProvider getAdapters()
     */
    public function testUniqueIndexes($adapter)
    {
        // make sure the foo_u index was created
        $keys = $this->sql('uniqueKeys', $adapter);
        $this->assertCount(1, $keys, 'test schema should have one unique key');
        $this->assertRegExp('/foo_u.*?\(.?user_id.?,.?name.?\)/m', $keys[0], 'unique key `foo_u` on columns `user_id`,`name` should exist');
    }

    /**
     * @dataProvider getAdapters()
     * @depends testSchemaCorrectTables
     * @depends testSchemaCorrectSeeds
     * @depends testPrimaryKeys
     * @depends testForeignKeysAfterCreate
     * @depends testForeignKeyActions
     * @depends testNormalIndexes
     * @depends testUniqueIndexes
     */
    public function testTheWholeThing($adapter)
    {
        $manager = static::$managers[$adapter];
        $adapterObj = $manager->getEnvironment('phpunit')->getAdapter();
        // kill and recreate the test schema
        foreach(array('test_seed','test_seed2','foo_users','testing_seed_log') as $t) {
            if( $adapterObj->hasTable($t) )
                $adapterObj->dropTable($t);
        }
        foreach(static::$schema_statements[$adapter] as $sql) {
            $adapterObj->query($sql);
        } 
    }

    //
    // ----- helper functions -----
    //
    
    private function sql($type, $adapter) 
    {
        $this->getSqlInfo($adapter);
        return isset(static::$sql_info[$adapter][$type]) ? static::$sql_info[$adapter][$type] : array();
    }

    private static function categorizeSqlStatements($sql_list)
    {
        $info = array(
            'max_create_line'=>-1,
            'min_fk_line'=>-1,
            'dumped_migration_structure'=>false,
            'dumped_migration_data'=>false
        );
        foreach( array('creates','inserts','foreignKeys','primaryKeys','uniqueKeys','normalKeys') as $var ) {
            $info[$var] = array();
        }

        $line = 0;

        foreach( $sql_list as $sql )
        {
            if( preg_match('/^CREATE TABLE/i', $sql) ) {
                $info['creates'][] = $sql;
                $info['max_create_line']=$line;
                if( preg_match('/\btesting_seed_log\b/i', $sql) )
                    $info['dumped_migration_structure']=true;
            }

            if( preg_match('/^INSERT INTO/i', $sql) ) {
                $info['inserts'][] = $sql;
                if( preg_match('/\btesting_seed_log\b/i', $sql) )
                    $info['dumped_migration_data']=true;
            }

            if( preg_match('/FOREIGN KEY/mi', $sql) ) {
                $info['foreignKeys'][]=$sql;
                if( $info['min_fk_line'] == -1 )
                    $info['min_fk_line'] = $line;
            }

            if( preg_match('/PRIMARY KEY/mi', $sql) ) {
                $info['primaryKeys'][]=$sql;
            }

            if( preg_match('/\bfoo_u\b/mi', $sql) ) {
                $info['uniqueKeys'][]=$sql;
            }

            if( preg_match('/\bfoo_nu\b/mi', $sql) ) {
                $info['normalKeys'][]=$sql;
            }

            $line++;
        }
        return $info;
    }

    private function getSqlInfo($adapter)
    {
        if( !isset(static::$sql_info[$adapter] )) {
            $raw_sql = $this->getSchemaDump($adapter);
            $sql_list = SqlParser::parse($raw_sql);
            $info = static::categorizeSqlStatements($sql_list);
            static::$sql_info[$adapter] = $info;
            static::$schema_statements[$adapter]=$sql_list;
        }
        return static::$sql_info[$adapter];
    }

    private function getSchemaDump($adapter) 
    {
        if(!isset(static::$schema_dumps[$adapter])) {
            $this->cache($adapter);
        }
        return static::$schema_dumps[$adapter];
    }

    private function cache($adapter)
    {
        $out_file = static::$dump_files[$adapter];

        static::$managers[$adapter]->dumpSchema('phpunit', $out_file);
        $this->assertTrue(filesize($out_file) > 0);

        $raw_sql = file_get_contents($out_file);
        static::$schema_dumps[$adapter]=$raw_sql;
    }
}
