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

namespace Test\Phinx\ComplexSchemaDumpTest;

use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\SqlParser;
use Phinx\Migration\Manager;

class ComplexSchemaDumpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $temp_file;

    # only use one adapter, but change here if necessary
    private static $adapter = 'mysql';


    /**
     * @dataProvider getData()
     */
    public function testCorrectInserts($name, $expected_inserts, $expected_fks, $schema_commands)
    {
        $this->manager->setConfig($this->getConfig($schema_commands));

        $a = $this->manager->getEnvironment('phpunit')->getAdapter();

        // create the schema
        foreach ($schema_commands as $sql) {
            $a->query($sql);
        }
        
        // dump the schema
        $this->manager->dumpSchema('phpunit', $this->temp_file);
        $dumped = file_get_contents($this->temp_file);
        //print_r($dumped);
        $dumped_sql = SqlParser::parse($dumped);
        $inserts=array();
        $fks=array();

        foreach ($dumped_sql as $sql) {
            if (preg_match('/^INSERT INTO/i', $sql)) {
                $inserts[] = $sql;
            }
            if (preg_match('/FOREIGN KEY/i', $sql)) {
                $fks[] = $sql;
            }
        }

        $this->assertCount($expected_inserts, $inserts);
        $this->assertCount($expected_fks, $fks);

        if ($name == 'circularDependency') {
            // make sure the user is warned about circular dependency
            $s = $this->manager->getOutput()->getStream();
            rewind($s);
            $console = stream_get_contents($s);
            $this->assertRegExp('/circular dependency detected/i', $console);

            // TODO: dump data or throw exception?
        } else {
            // dump and load the schema
            $a = $this->manager->getEnvironment('phpunit')->getAdapter();

            $conf = $this->manager->getConfig();
            if (static::$adapter=='pgsql') {
                $a->dropAllSchemas();
                $a->createSchema($conf['environments']['phpunit']['schema']);
            } else {
                $a->dropDatabase($conf['environments']['phpunit']['name']);
                $a->createDatabase($conf['environments']['phpunit']['name']);
            }
            $a->disconnect();
            foreach ($dumped_sql as $cmd) {
                $a->execute($cmd);
            }
        }
    }

    public function setUp()
    {
        $conf = $this->getConfig(array());
        $this->manager = new Manager($conf, new StreamOutput(fopen('php://memory', 'a')));
        $a = $this->manager->getEnvironment('phpunit')->getAdapter();

        // kill the schema competely
        if (static::$adapter=='pgsql') {
            $a->dropAllSchemas();
            $a->createSchema($conf['environments']['phpunit']['schema']);
        } else {
            $a->dropDatabase($conf['environments']['phpunit']['name']);
            if (static::$adapter=='mysql') {
                $a->query('set storage_engine=InnoDB');
            }
            $a->createDatabase($conf['environments']['phpunit']['name']);
        }
        $a->disconnect();
        if (static::$adapter=='mysql') {
            $a->query('set storage_engine=InnoDB');
        }
        $a->createSchemaTable();

        $this->temp_file = tempnam(sys_get_temp_dir(), "phinxDump_complex");
    }

    public function tearDown()
    {
        unlink($this->temp_file);
    }

    /*
     * data provider for tests
     */
    public static function getData()
    {

        // return a list of complicated schema to run a round trip dump and import on
        // each schema will have a 
        $data = array(
            array( 'nestedDependencies', 9, 2,
                   <<<SQL
create table t3(id int not null primary key, t2_id int, v varchar(10));
insert into t3 select 1, 1, 'm1.det1.1' union select 2, 1, 'm1.det1.2' union select 3, 2, 'm1.det2.1';
create table t2(id int not null primary key, t1_id int, v varchar(10));
insert into t2 select 1, 1,'m1.det1' union select 2,1,'m1.det2' union select 3,2,'m2.det1';
create table t1(id int not null primary key, v varchar(10));
insert into t1 select 1, 'master1' union select 2,'master2' union select 3,'master3';

alter table t3 add constraint t3_fk foreign key ( t2_id ) references t2 ( id ) on delete no action on update no action;
alter table t2 add constraint t2_fk foreign key ( t1_id ) references t1 ( id ) on delete no action on update no action;
SQL
                ),
            array( 'multipleParents', 4, 3,
                   <<<SQL
create table kid1(id int not null primary key, p1_id int, v varchar(10));
create table kid2(id int not null primary key, p1_id int, p2_id int, v varchar(10));
create table parent1(id int not null primary key, v varchar(10));
create table parent2(id int not null primary key, v varchar(10));

alter table kid1 add constraint kid1_fk foreign key ( p1_id ) references parent1( id ) on delete no action on update no action;
alter table kid2 add constraint kid2_fk foreign key ( p1_id ) references parent1( id ) on delete no action on update no action;
alter table kid2 add constraint kid2_fk2 foreign key ( p2_id ) references parent2( id ) on delete no action on update no action;

insert into parent1 select 1, 'a,b mom';
insert into parent2 select 1, 'b dad';
insert into kid1 select 1, 1, 'alice';
insert into kid2 select 1, 1, 1, 'bob';
SQL
            ),
            array( 'mulitpleFks', 3, 2,
                    <<<SQL
create table parent( id int not null primary key, name varchar(10), kid_id int, pet_id int );
create table kid(id int not null primary key, name varchar(10));
create table pet(id int not null primary key, name varchar(10));

alter table parent add constraint p_kid_fk foreign key ( kid_id ) references kid ( id ) on delete no action on update no action;
alter table parent add constraint p_pet_fk foreign key ( pet_id ) references pet ( id ) on delete no action on update no action;

insert into kid select 1, 'billy';
insert into pet select 1, 'fido';
insert into parent select 1, 'dad', 1, 1;
SQL
            ),
            array( 'circularDependency', 2, 2,
                    <<<SQL
create table city( id int not null primary key, name varchar(30), state_id int);
create table state( id int not null primary key, name varchar(30), capital_city_id int);

insert into city select 1,'sacramento',1;
insert into state select 1,'california',1;

alter table city add constraint city_fk foreign key ( state_id ) references state(id) on delete no action on update no action;
alter table state add constraint state_fk foreign key ( capital_city_id ) references city(id) on delete no action on update no action;
SQL
        ));
         
        return array_map(function ($a) { return array($a[0], $a[1], $a[2], SqlParser::parse($a[3])); }, $data);
    }

    //
    // -- helper functions
    //
    private function getConfig($script_commands=array(), $name=null)
    {
        $prefix = 'TESTS_PHINX_DB_ADAPTER_' . strtoupper(static::$adapter);
        $table_list = array();
        foreach ($script_commands as $cmd) {
            if (preg_match('/^create table [\'`"]?(\w+)\b/i', $cmd, $m)) {
                $table_list[]=$m[1];
            }
        }
        $conf = array(
            'paths' => array(
                'schema' => '/tmp/idontexist'),
            'seeds' => array(
                'tables' => $table_list
            ),
            'environments' => array(
                'default_migration_table' => uniqid('migration_'),
                'default_environment' => 'phpunit',
                'phpunit' => array(
                    'adapter' => static::$adapter,
                    'host' => constant("${prefix}_HOST"),
                    'name' => constant("${prefix}_DATABASE"),
                    'user' => constant("${prefix}_USERNAME"),
                    'pass' => constant("${prefix}_PASSWORD"),
                    'port' => constant("${prefix}_PORT")
                )
            ));
        if (static::$adapter == 'pgsql') {
            $conf['environments']['phpunit']['schema'] = constant("${prefix}_DATABASE_SCHMA");
        }

        return new Config($conf);
    }
}
