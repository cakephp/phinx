<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class PDOMock extends \PDO
{
    public function __construct()
    {
    }
}

class MysqlAdapterTester extends MysqlAdapter
{
    public function setMockConnection($connection)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    // change visibility for testing
    public function getDefaultValueDefinition($default)
    {
        return parent::getDefaultValueDefinition($default);
    }

    public function getColumnSqlDefinition(Column $column)
    {
        return parent::getColumnSqlDefinition($column);
    }

    public function getIndexSqlDefinition(Index $index)
    {
        return parent::getIndexSqlDefinition($index);
    }

    public function getIndexes($tableName)
    {
        return parent::getIndexes($tableName);
    }

    public function getForeignKeys($tableName)
    {
        return parent::getForeignKeys($tableName);
    }
}

class MysqlAdapterUnitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MysqlAdapterTester
     */
    private $adapter;

    private $conn;

    private $result;

    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED) {
            $this->markTestSkipped('Mysql tests disabled. See TESTS_PHINX_DB_ADAPTER_MYSQL_ENABLED constant.');
        }

        $this->adapter = new MysqlAdapterTester([], new ArrayInput([]), new NullOutput());

        $this->conn = $this->getMockBuilder('PDOMock')
                           ->disableOriginalConstructor()
                           ->setMethods([ 'query', 'exec', 'quote' ])
                           ->getMock();
        $this->result = $this->getMockBuilder('stdclass')
                             ->disableOriginalConstructor()
                             ->setMethods([ 'fetch' ])
                             ->getMock();
        $this->adapter->setMockConnection($this->conn);
    }

    // helper methods for easy mocking
    private function assertExecuteSql($expected_sql)
    {
        $this->conn->expects($this->once())
                   ->method('exec')
                   ->with($this->equalTo($expected_sql));
    }

    private function assertQuerySql($expectedSql, $returnValue = null)
    {
        $expect = $this->conn->expects($this->once())
                       ->method('query')
                       ->with($this->equalTo($expectedSql));
        if (!is_null($returnValue)) {
            $expect->will($this->returnValue($returnValue));
        }
    }

    private function assertFetchRowSql($expectedSql, $returnValue)
    {
        $this->result->expects($this->once())
                     ->method('fetch')
                     ->will($this->returnValue($returnValue));
        $this->assertQuerySql($expectedSql, $this->result);
    }

    public function testDisconnect()
    {
        $this->assertNotNull($this->adapter->getConnection());
        $this->adapter->disconnect();
        $this->assertNull($this->adapter->getConnection());
    }

    // database related tests

    public function testHasDatabaseExists()
    {
        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue(['SCHEMA_NAME' => 'database_name']));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'database_name'", $this->result);

        $this->assertTrue($this->adapter->hasDatabase('database_name'));
    }

    public function testHasDatabaseNotExists()
    {
        $this->result->expects($this->once())
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'database_name2'", $this->result);

        $this->assertFalse($this->adapter->hasDatabase('database_name2'));
    }

    public function testDropDatabase()
    {
        $this->assertExecuteSql("DROP DATABASE IF EXISTS `database_name`");
        $this->adapter->dropDatabase('database_name');
    }

    public function testCreateDatabase()
    {
        $this->assertExecuteSql("CREATE DATABASE `database_name` DEFAULT CHARACTER SET `utf8`");
        $this->adapter->createDatabase('database_name');
    }

    public function testCreateDatabaseWithCharset()
    {
        $this->assertExecuteSql("CREATE DATABASE `database_name` DEFAULT CHARACTER SET `latin1`");
        $this->adapter->createDatabase('database_name', ['charset' => 'latin1']);
    }

    public function testCreateDatabaseWithCharsetAndCollation()
    {
        $this->assertExecuteSql("CREATE DATABASE `database_name` DEFAULT CHARACTER SET `latin1` COLLATE `latin1_swedish_ci`");
        $this->adapter->createDatabase('database_name', ['charset' => 'latin1', 'collation' => 'latin1_swedish_ci']);
    }

    public function testHasTransactions()
    {
        $this->assertTrue($this->adapter->hasTransactions());
    }

    public function testBeginTransaction()
    {
        $this->assertExecuteSql("START TRANSACTION");
        $this->adapter->beginTransaction();
    }

    public function testCommitTransaction()
    {
        $this->assertExecuteSql("COMMIT");
        $this->adapter->commitTransaction();
    }

    public function testRollbackTransaction()
    {
        $this->assertExecuteSql("ROLLBACK");
        $this->adapter->rollbackTransaction();
    }

    // table related tests

    public function testDescribeTable()
    {
        $this->adapter->setOptions(['name' => 'database_name']);

        $expectedSql = "SELECT *
             FROM information_schema.tables
             WHERE table_schema = 'database_name'
             AND table_name = 'table_name'";

        $returnValue = ['TABLE_TYPE' => 'BASE_TABLE',
                             'TABLE_NAME' => 'table_name',
                             'TABLE_SCHEMA' => 'database_name',
                             'TABLE_ROWS' => 0];
        $this->assertFetchRowSql($expectedSql, $returnValue);

        $described = $this->adapter->describeTable('table_name');
        $this->assertEquals($returnValue, $described);
    }

    public function testRenameTable()
    {
        $this->assertExecuteSql("RENAME TABLE `old_table_name` TO `new_table_name`");
        $this->adapter->renameTable('old_table_name', 'new_table_name');
    }

    public function testDropTable()
    {
        $this->assertExecuteSql("DROP TABLE `table_name`");
        $this->adapter->dropTable("table_name");
    }

    public function testTruncateTable()
    {
        $this->assertExecuteSql("TRUNCATE TABLE `table_name`");
        $this->adapter->truncateTable("table_name");
    }

    public function testHasTableExists()
    {
        $this->adapter->setOptions(['name' => 'database_name']);
        $this->result->expects($this->once())
                     ->method('fetch')
                     ->will($this->returnValue(['somecontent']));
        $expectedSql = 'SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = \'database_name\' AND TABLE_NAME = \'table_name\'';
        $this->assertQuerySql($expectedSql, $this->result);
        $this->assertTrue($this->adapter->hasTable("table_name"));
    }

    public function testHasTableNotExists()
    {
        $this->adapter->setOptions(['name' => 'database_name']);
        $this->result->expects($this->once())
                     ->method('fetch')
                     ->will($this->returnValue([]));
        $expectedSql = 'SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = \'database_name\' AND TABLE_NAME = \'table_name\'';
        $this->assertQuerySql($expectedSql, $this->result);
        $this->assertFalse($this->adapter->hasTable("table_name"));
    }

    public function testCreateTableBasic()
    {
        $column1 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column1->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column1->expects($this->any())->method('getType')->will($this->returnValue('string'));
        $column1->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column1->expects($this->at(0))->method('getLimit')->will($this->returnValue('64'));

        $column2 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column2->expects($this->any())->method('getName')->will($this->returnValue('column_name2'));
        $column2->expects($this->any())->method('getType')->will($this->returnValue('integer'));
        $column2->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column2->expects($this->at(0))->method('getLimit')->will($this->returnValue('4'));

        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName', 'getOptions', 'getPendingColumns', 'getIndexes', 'getForeignKeys'])
                      ->getMock();

        $table->expects($this->any())->method('getPendingColumns')->will($this->returnValue([$column1, $column2]));
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));
        $table->expects($this->any())->method('getOptions')->will($this->returnValue([]));
        $table->expects($this->any())->method('getIndexes')->will($this->returnValue([]));
        $table->expects($this->any())->method('getForeignKeys')->will($this->returnValue([]));

        $expectedSql = 'CREATE TABLE `table_name` (`id` INT(11) NOT NULL AUTO_INCREMENT, `column_name` VARCHAR(255) NOT NULL, `column_name2` INT(11) NOT NULL, PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;';
        $this->assertExecuteSql($expectedSql);
        $this->adapter->createTable($table);
    }

    public function testCreateTablePrimaryKey()
    {
        $column1 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column1->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column1->expects($this->any())->method('getType')->will($this->returnValue('string'));
        $column1->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column1->expects($this->at(0))->method('getLimit')->will($this->returnValue('64'));

        $column2 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column2->expects($this->any())->method('getName')->will($this->returnValue('column_name2'));
        $column2->expects($this->any())->method('getType')->will($this->returnValue('integer'));
        $column2->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column2->expects($this->at(0))->method('getLimit')->will($this->returnValue('4'));

        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName', 'getOptions', 'getPendingColumns', 'getIndexes', 'getForeignKeys'])
                      ->getMock();

        $tableOptions = ['id' => 'column_name2'];
        $table->expects($this->any())->method('getPendingColumns')->will($this->returnValue([$column1, $column2]));
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));
        $table->expects($this->any())->method('getOptions')->will($this->returnValue($tableOptions));
        $table->expects($this->any())->method('getIndexes')->will($this->returnValue([]));
        $table->expects($this->any())->method('getForeignKeys')->will($this->returnValue([]));

        $expectedSql = 'CREATE TABLE `table_name` (`column_name2` INT(11) NOT NULL AUTO_INCREMENT, `column_name` VARCHAR(255) NOT NULL, `column_name2` INT(11) NOT NULL, PRIMARY KEY (`column_name2`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;';
        $this->assertExecuteSql($expectedSql);
        $this->adapter->createTable($table);
    }

    public function testCreateTableUnsignedPK()
    {
        $column1 = $this->getMockBuilder('Phinx\Db\Table\Column')
                        ->disableOriginalConstructor()
                        ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                        ->getMock();

        $column1->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column1->expects($this->any())->method('getType')->will($this->returnValue('string'));
        $column1->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column1->expects($this->at(0))->method('getLimit')->will($this->returnValue('64'));

        $column2 = $this->getMockBuilder('Phinx\Db\Table\Column')
                        ->disableOriginalConstructor()
                        ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                        ->getMock();

        $column2->expects($this->any())->method('getName')->will($this->returnValue('column_name2'));
        $column2->expects($this->any())->method('getType')->will($this->returnValue('integer'));
        $column2->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column2->expects($this->at(0))->method('getLimit')->will($this->returnValue('4'));

        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName', 'getOptions', 'getPendingColumns', 'getIndexes', 'getForeignKeys'])
                      ->getMock();

        $tableOptions = ['signed' => false];
        $table->expects($this->any())->method('getPendingColumns')->will($this->returnValue([$column1, $column2]));
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));
        $table->expects($this->any())->method('getOptions')->will($this->returnValue($tableOptions));
        $table->expects($this->any())->method('getIndexes')->will($this->returnValue([]));
        $table->expects($this->any())->method('getForeignKeys')->will($this->returnValue([]));

        $expectedSql = 'CREATE TABLE `table_name` (`id` INT(11) unsigned NOT NULL AUTO_INCREMENT, `column_name` VARCHAR(255) NOT NULL, `column_name2` INT(11) NOT NULL, PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;';
        $this->assertExecuteSql($expectedSql);
        $this->adapter->createTable($table);
    }

    public function testCreateTableAdvanced()
    {
        $refTable = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName', 'getOptions', 'getPendingColumns', 'getIndexes', 'getForeignKeys'])
                      ->getMock();
        $refTable->expects($this->any())->method('getName')->will($this->returnValue('other_table'));

        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName', 'getOptions', 'getPendingColumns', 'getIndexes', 'getForeignKeys'])
                      ->getMock();

        $tableOptions = ['collation' => 'latin1_swedish_ci',
                              'engine' => 'MyISAM',
                              'id' => ['ref_id', 'other_table_id'],
                              'primary_key' => ['ref_id', 'other_table_id'],
                              'comment' => "Table Comment"];
        $this->conn->expects($this->any())->method('quote')->with('Table Comment')->will($this->returnValue('`Table Comment`'));

        $column1 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column1->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column1->expects($this->any())->method('getType')->will($this->returnValue('string'));
        $column1->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column1->expects($this->at(0))->method('getLimit')->will($this->returnValue('64'));

        $column2 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column2->expects($this->any())->method('getName')->will($this->returnValue('other_table_id'));
        $column2->expects($this->any())->method('getType')->will($this->returnValue('integer'));
        $column2->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column2->expects($this->at(0))->method('getLimit')->will($this->returnValue('4'));

        $column3 = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column3->expects($this->any())->method('getName')->will($this->returnValue('ref_id'));
        $column3->expects($this->any())->method('getType')->will($this->returnValue('integer'));
        $column3->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column3->expects($this->at(0))->method('getLimit')->will($this->returnValue('11'));

        $index = $this->getMockBuilder('Phinx\Db\Table\Index')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getColumns'])
                      ->getMock();

        $index->expects($this->any())->method('getColumns')->will($this->returnValue(['column_name']));

        $foreignkey = $this->getMockBuilder('Phinx\Db\Table\ForeignKey')
                           ->disableOriginalConstructor()
                           ->setMethods([ 'getColumns',
                                               'getConstraint',
                                               'getReferencedColumns',
                                               'getOnDelete',
                                               'getOnUpdate',
                                               'getReferencedTable'])
                           ->getMock();

        $foreignkey->expects($this->any())->method('getColumns')->will($this->returnValue(['other_table_id']));
        $foreignkey->expects($this->any())->method('getConstraint')->will($this->returnValue('fk1'));
        $foreignkey->expects($this->any())->method('getReferencedColumns')->will($this->returnValue(['id']));
        $foreignkey->expects($this->any())->method('getReferencedTable')->will($this->returnValue($refTable));
        $foreignkey->expects($this->any())->method('getOnDelete')->will($this->returnValue(null));
        $foreignkey->expects($this->any())->method('getOnUpdate')->will($this->returnValue(null));

        $table->expects($this->any())->method('getPendingColumns')->will($this->returnValue([$column1, $column2, $column3]));
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));
        $table->expects($this->any())->method('getOptions')->will($this->returnValue($tableOptions));
        $table->expects($this->any())->method('getIndexes')->will($this->returnValue([$index]));
        $table->expects($this->any())->method('getForeignKeys')->will($this->returnValue([$foreignkey]));

        $expectedSql = 'CREATE TABLE `table_name` (`column_name` VARCHAR(255) NOT NULL, `other_table_id` INT(11) NOT NULL, `ref_id` INT(11) NOT NULL, PRIMARY KEY (`ref_id`,`other_table_id`),  KEY (`column_name`),  CONSTRAINT `fk1` FOREIGN KEY (`other_table_id`) REFERENCES `other_table` (`id`)) ENGINE = MyISAM CHARACTER SET latin1 COLLATE latin1_swedish_ci COMMENT=`Table Comment`;';
        $this->assertExecuteSql($expectedSql);
        $this->adapter->createTable($table);
    }

    /**
     * @todo not real unit, Column class is not mocked, improve dependency of Column removing new. Could be done calling protected newColumn() and override newColumn() in tester class
     *
     */
    public function testGetColumns()
    {
        $column1 = [
            'Field' => 'column1',
            'Type' => 'int(15)',
            'Null' => 'NO',
            'Default' => '',
            'Key' => 'PRI',
            'Extra' => 'auto_increment'
        ];

        $column2 = [
            'Field' => 'column2',
            'Type' => 'varchar(32)',
            'Null' => '',
            'Default' => 'NULL',
            'Key' => '',
            'Extra' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($column1));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($column2));
        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("SHOW COLUMNS FROM `table_name`", $this->result);

        $columns = $this->adapter->getColumns("table_name");

        $this->assertTrue(is_array($columns));
        $this->assertEquals(2, count($columns));

        $this->assertEquals('column1', $columns[0]->getName());
        $this->assertInstanceOf('Phinx\Db\Table\Column', $columns[0]);
        $this->assertEquals('15', $columns[0]->getLimit());
        $this->assertFalse($columns[0]->getNull());
        $this->assertEquals('', $columns[0]->getDefault());
        $this->assertTrue($columns[0]->getIdentity());

        $this->assertEquals('column2', $columns[1]->getName());
        $this->assertInstanceOf('Phinx\Db\Table\Column', $columns[1]);
        $this->assertEquals('32', $columns[1]->getLimit());
        $this->assertTrue($columns[1]->getNull());
        $this->assertEquals('NULL', $columns[1]->getDefault());
        $this->assertFalse($columns[1]->getIdentity());
    }

    // column related tests

    public function testHasColumnExists()
    {
        $column1 = [
            'Field' => 'column1',
            'Type' => 'int(15)',
            'Null' => 'NO',
            'Default' => '',
            'Extra' => 'auto_increment'
        ];

        $column2 = [
            'Field' => 'column2',
            'Type' => 'varchar(32)',
            'Null' => '',
            'Default' => 'NULL',
            'Extra' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($column1));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($column2));
        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql('SHOW COLUMNS FROM `table_name`', $this->result);

        $this->assertTrue($this->adapter->hasColumn('table_name', 'column1'));
    }

    public function testGetColumnSqlDefinitionInteger()
    {
        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column->expects($this->any())->method('getLimit')->will($this->returnValue('11'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('integer'));

        $this->assertEquals(
            "INT(11) NOT NULL",
            $this->adapter->getColumnSqlDefinition($column)
        );
    }

    public function testGetColumnSqlDefinitionFloat()
    {
        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit', 'getScale', 'getPrecision'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('float'));
        $column->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column->expects($this->any())->method('getPrecision')->will($this->returnValue('8'));
        $column->expects($this->any())->method('getScale')->will($this->returnValue('3'));

        $this->assertEquals(
            "FLOAT(8,3) NOT NULL",
            $this->adapter->getColumnSqlDefinition($column)
        );
    }

    /**
     * @todo must enter in code that removes limit
     */
    public function testGetColumnSqlDefinitionTextWithLimit()
    {
        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit', 'setLimit'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('text'));
        $column->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column->expects($this->at(0))->method('getLimit')->will($this->returnValue('2048'));
        $column->expects($this->at(1))->method('getLimit')->will($this->returnValue(null));

        $this->assertEquals(
            "TEXT NOT NULL",
            $this->adapter->getColumnSqlDefinition($column)
        );
    }

    public function testGetColumnSqlDefinitionComplete()
    {
        $this->conn->expects($this->once())
                   ->method('quote')
                   ->with($this->equalTo('Custom Comment'))
                   ->will($this->returnValue("`Custom Comment`"));

        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName',
                                          'getAfter',
                                          'getType',
                                          'getLimit',
                                          'getScale',
                                          'getPrecision',
                                          'getComment',
                                          'isIdentity',
                                          'getUpdate'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column->expects($this->any())->method('isIdentity')->will($this->returnValue(true));
        $column->expects($this->any())->method('getComment')->will($this->returnValue('Custom Comment'));
        $column->expects($this->any())->method('getUpdate')->will($this->returnValue('CASCADE'));
        $column->expects($this->any())->method('getLimit')->will($this->returnValue(''));
        $column->expects($this->any())->method('getScale')->will($this->returnValue('2'));
        $column->expects($this->any())->method('getPrecision')->will($this->returnValue('8'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('float'));

        $this->assertEquals(
            "FLOAT(8,2) NOT NULL AUTO_INCREMENT COMMENT `Custom Comment` ON UPDATE CASCADE",
            $this->adapter->getColumnSqlDefinition($column)
        );
    }

    public function testHasColumnExistsCaseInsensitive()
    {
        $column1 = [
            'Field' => 'column1',
            'Type' => 'int(15)',
            'Null' => 'NO',
            'Default' => '',
            'Extra' => 'auto_increment'
        ];

        $column2 = [
            'Field' => 'column2',
            'Type' => 'varchar(32)',
            'Null' => '',
            'Default' => 'NULL',
            'Extra' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($column1));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($column2));
        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql('SHOW COLUMNS FROM `table_name`', $this->result);

        $this->assertTrue($this->adapter->hasColumn('table_name', 'CoLumN1'));
    }

    public function testHasColumnNotExists()
    {
        $column1 = [
            'Field' => 'column1',
            'Type' => 'int(15)',
            'Null' => 'NO',
            'Default' => '',
            'Extra' => 'auto_increment'
        ];

        $column2 = [
            'Field' => 'column2',
            'Type' => 'varchar(32)',
            'Null' => '',
            'Default' => 'NULL',
            'Extra' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($column1));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($column2));
        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql('SHOW COLUMNS FROM `table_name`', $this->result);

        $this->assertFalse($this->adapter->hasColumn('table_name', 'column3'));
    }

    public function testDropColumn()
    {
        $this->assertExecuteSql("ALTER TABLE `table_name` DROP COLUMN `column1`");
        $this->adapter->dropColumn('table_name', 'column1');
    }

    public function testAddColumn()
    {
        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName'])
                      ->getMock();
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));

        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getAfter')->will($this->returnValue(null));
        $column->expects($this->any())->method('getLimit')->will($this->returnValue('11'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('integer'));

        $this->assertExecuteSql('ALTER TABLE `table_name` ADD `column_name` INT(11) NOT NULL');
        $this->adapter->addColumn($table, $column);
    }

    public function testAddColumnWithAfter()
    {
        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName'])
                      ->getMock();
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));

        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getAfter')->will($this->returnValue('column_name2'));
        $column->expects($this->any())->method('getLimit')->will($this->returnValue('11'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('integer'));

        $this->assertExecuteSql('ALTER TABLE `table_name` ADD `column_name` INT(11) NOT NULL AFTER `column_name2`');
        $this->adapter->addColumn($table, $column);
    }

    public function testChangeColumn()
    {
        $column = $this->getMockBuilder('Phinx\Db\Table\Column')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getName', 'getAfter', 'getType', 'getLimit'])
                      ->getMock();

        $column->expects($this->any())->method('getName')->will($this->returnValue('column_name'));
        $column->expects($this->any())->method('getLimit')->will($this->returnValue('11'));
        $column->expects($this->any())->method('getType')->will($this->returnValue('integer'));

        $this->assertExecuteSql('ALTER TABLE `table_name` CHANGE `column1` `column_name` INT(11) NOT NULL');
        $this->adapter->changeColumn('table_name', 'column1', $column);
    }

    public function testRenameColumnExists()
    {
        $column1 = [
            'Field' => 'column_old',
            'Type' => 'int(15)',
            'Null' => 'NO',
            'Default' => '',
            'Extra' => 'auto_increment'
        ];

        $column2 = [
            'Field' => 'column2',
            'Type' => 'varchar(32)',
            'Null' => '',
            'Default' => 'NULL',
            'Extra' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($column1));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($column2));
        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("DESCRIBE `table_name`", $this->result);

        $this->assertExecuteSql('ALTER TABLE `table_name` CHANGE COLUMN `column_old` `column_new` int(15) NOT NULL AUTO_INCREMENT');
        $this->adapter->renameColumn('table_name', 'column_old', 'column_new');
    }

    public function testRenameColumnNotExists()
    {
        $column1 = [
            'Field' => 'column1',
            'Type' => 'int(15)',
            'Null' => 'NO',
            'Default' => '',
            'Extra' => 'auto_increment'
        ];

        $column2 = [
            'Field' => 'column2',
            'Type' => 'varchar(32)',
            'Null' => '',
            'Default' => 'NULL',
            'Extra' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($column1));
        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($column2));
        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("DESCRIBE `table_name`", $this->result);

        $this->setExpectedException('\InvalidArgumentException', 'The specified column doesn\'t exist: column_old');
        $this->adapter->renameColumn('table_name', 'column_old', 'column_new');
    }

    public function testGetDefaultValueDefinitionEmpty()
    {
        $this->assertEquals('', $this->adapter->getDefaultValueDefinition(null));
        $this->assertEquals('', $this->adapter->getDefaultValueDefinition('NULL'));
    }

    public function testGetDefaultValueDefinitionBoolean()
    {
        $this->assertEquals(
            ' DEFAULT 1',
            $this->adapter->getDefaultValueDefinition(true)
        );
    }

    public function testGetDefaultValueDefinitionInteger()
    {
        $this->assertEquals(
            ' DEFAULT 5',
            $this->adapter->getDefaultValueDefinition(5)
        );
    }

    public function testGetDefaultValueDefinitionCurrentTimestamp()
    {
        $this->assertEquals(
            ' DEFAULT CURRENT_TIMESTAMP',
            $this->adapter->getDefaultValueDefinition('CURRENT_TIMESTAMP')
        );
    }

    public function testGetDefaultValueDefinitionString()
    {
        $this->conn->expects($this->once())
                   ->method('quote')
                   ->with($this->equalTo('str'))
                   ->will($this->returnValue("`str`"));
        $this->assertEquals(' DEFAULT `str`', $this->adapter->getDefaultValueDefinition('str'));
    }

    public function testGetSqlTypeExists()
    {
        $this->assertEquals(
            ['name' => 'varchar', 'limit' => 255],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_STRING)
        );
        $this->assertEquals(
            ['name' => 'char', 'limit' => 255],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_CHAR, 255)
        );

        //text combinations
        $this->assertEquals(
            ['name' => 'text'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT)
        );
        $this->assertEquals(
            ['name' => 'tinytext'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_TINY)
        );
        $this->assertEquals(
            ['name' => 'tinytext'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_TINY + 1)
        );
        $this->assertEquals(
            ['name' => 'text'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_REGULAR)
        );
        $this->assertEquals(
            ['name' => 'text'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_REGULAR + 1)
        );
        $this->assertEquals(
            ['name' => 'mediumtext'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_MEDIUM)
        );
        $this->assertEquals(
            ['name' => 'mediumtext'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_MEDIUM + 1)
        );
        $this->assertEquals(
            ['name' => 'longtext'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_LONG)
        );
        $this->assertEquals(
            ['name' => 'longtext'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TEXT, MysqlAdapter::TEXT_LONG + 1)
        );

        //blob combinations
        $this->assertEquals(
            ['name' => 'blob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB)
        );
        $this->assertEquals(
            ['name' => 'tinyblob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_TINY)
        );
        $this->assertEquals(
            ['name' => 'tinyblob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_TINY + 1)
        );
        $this->assertEquals(
            ['name' => 'blob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_REGULAR)
        );
        $this->assertEquals(
            ['name' => 'blob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_REGULAR + 1)
        );
        $this->assertEquals(
            ['name' => 'mediumblob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_MEDIUM)
        );
        $this->assertEquals(
            ['name' => 'mediumblob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_MEDIUM + 1)
        );
        $this->assertEquals(
            ['name' => 'longblob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_LONG)
        );
        $this->assertEquals(
            ['name' => 'longblob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB, MysqlAdapter::BLOB_LONG + 1)
        );

        $this->assertEquals(
            ['name' => 'binary', 'limit' => 255],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BINARY)
        );
        $this->assertEquals(
            ['name' => 'binary', 'limit' => 36],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BINARY, 36)
        );

        $this->assertEquals(
            ['name' => 'varbinary', 'limit' => 255],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_VARBINARY)
        );
        $this->assertEquals(
            ['name' => 'varbinary', 'limit' => 16],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_VARBINARY, 16)
        );

        //int combinations
        $this->assertEquals(
            ['name' => 'int', 'limit' => 11],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER)
        );
        $this->assertEquals(
            ['name' => 'bigint', 'limit' => 20],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BIG_INTEGER)
        );
        $this->assertEquals(
            ['name' => 'tinyint'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_TINY)
        );
        $this->assertEquals(
            ['name' => 'tinyint'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_TINY + 1)
        );
        $this->assertEquals(
            ['name' => 'smallint'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_SMALL)
        );
        $this->assertEquals(
            ['name' => 'smallint'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_SMALL + 1)
        );
        $this->assertEquals(
            ['name' => 'mediumint'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_MEDIUM)
        );
        $this->assertEquals(
            ['name' => 'mediumint'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_MEDIUM + 1)
        );
        $this->assertEquals(
            ['name' => 'int', 'limit' => 11],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_REGULAR)
        );
        $this->assertEquals(
            ['name' => 'int', 'limit' => 11],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_REGULAR + 1)
        );
        $this->assertEquals(
            ['name' => 'bigint', 'limit' => 20],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_BIG)
        );
        $this->assertEquals(
            ['name' => 'bigint', 'limit' => 20],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_INTEGER, MysqlAdapter::INT_BIG + 1)
        );

        $this->assertEquals(
            ['name' => 'float'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_FLOAT)
        );
        $this->assertEquals(
            ['name' => 'decimal'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_DECIMAL)
        );
        $this->assertEquals(
            ['name' => 'datetime'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_DATETIME)
        );
        $this->assertEquals(
            ['name' => 'timestamp'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TIMESTAMP)
        );
        $this->assertEquals(
            ['name' => 'date'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_DATE)
        );
        $this->assertEquals(
            ['name' => 'time'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_TIME)
        );
        $this->assertEquals(
            ['name' => 'blob'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BLOB)
        );
        $this->assertEquals(
            ['name' => 'tinyint', 'limit' => 1],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_BOOLEAN)
        );
        $this->assertEquals(
            ['name' => 'geometry'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_GEOMETRY)
        );
        $this->assertEquals(
            ['name' => 'linestring'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_LINESTRING)
        );
        $this->assertEquals(
            ['name' => 'point'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_POINT)
        );
        $this->assertEquals(
            ['name' => 'polygon'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_POLYGON)
        );
        $this->assertEquals(
            ['name' => 'enum'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_ENUM)
        );
        $this->assertEquals(
            ['name' => 'set'],
            $this->adapter->getSqlType(MysqlAdapter::PHINX_TYPE_SET)
        );
    }

    public function testGetSqlTypeNotExists()
    {
        $this->setExpectedException('\RuntimeException', 'The type: "fake" is not supported.');
        $this->adapter->getSqlType('fake');
    }

    public function testPhinxTypeExistsWithoutLimit()
    {
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_STRING, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('varchar')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_CHAR, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('char')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_INTEGER, 'limit' => MysqlAdapter::INT_TINY, 'precision' => null],
            $this->adapter->getPhinxType('tinyint')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('int')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_INTEGER, 'limit' => MysqlAdapter::INT_SMALL, 'precision' => null],
            $this->adapter->getPhinxType('smallint')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_INTEGER, 'limit' => MysqlAdapter::INT_MEDIUM, 'precision' => null],
            $this->adapter->getPhinxType('mediumint')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('bigint')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BINARY, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('blob')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_VARBINARY, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('varbinary')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_FLOAT, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('float')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_DECIMAL, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('decimal')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_DATETIME, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('datetime')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TIMESTAMP, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('timestamp')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_DATE, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('date')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TIME, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('time')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TEXT, 'limit' => MysqlAdapter::TEXT_TINY, 'precision' => null],
            $this->adapter->getPhinxType('tinytext')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TEXT, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('text')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TEXT, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'precision' => null],
            $this->adapter->getPhinxType('mediumtext')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TEXT, 'limit' => MysqlAdapter::TEXT_LONG, 'precision' => null],
            $this->adapter->getPhinxType('longtext')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BINARY, 'limit' => MysqlAdapter::BLOB_TINY, 'precision' => null],
            $this->adapter->getPhinxType('tinyblob')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BINARY, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('blob')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BINARY, 'limit' => MysqlAdapter::BLOB_MEDIUM, 'precision' => null],
            $this->adapter->getPhinxType('mediumblob')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BINARY, 'limit' => MysqlAdapter::BLOB_LONG, 'precision' => null],
            $this->adapter->getPhinxType('longblob')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_POINT, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('point')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_GEOMETRY, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('geometry')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_LINESTRING, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('linestring')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_POLYGON, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('polygon')
        );
    }

    public function testPhinxTypeExistsWithLimit()
    {
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_STRING, 'limit' => 32, 'precision' => null],
            $this->adapter->getPhinxType('varchar(32)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_CHAR, 'limit' => 32, 'precision' => null],
            $this->adapter->getPhinxType('char(32)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_INTEGER, 'limit' => 12, 'precision' => null],
            $this->adapter->getPhinxType('int(12)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => 21, 'precision' => null],
            $this->adapter->getPhinxType('bigint(21)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BINARY, 'limit' => 1024, 'precision' => null],
            $this->adapter->getPhinxType('blob(1024)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_VARBINARY, 'limit' => 16, 'precision' => null],
            $this->adapter->getPhinxType('varbinary(16)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_FLOAT, 'limit' => 8, 'precision' => 2],
            $this->adapter->getPhinxType('float(8,2)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_DECIMAL, 'limit' => 8, 'precision' => 2],
            $this->adapter->getPhinxType('decimal(8,2)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_TEXT, 'limit' => 1024, 'precision' => null],
            $this->adapter->getPhinxType('text(1024)')
        );
    }

    public function testPhinxTypeExistsWithLimitNull()
    {
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_STRING, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('varchar(255)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_CHAR, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('char(255)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_INTEGER, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('int(11)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BIG_INTEGER, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('bigint(20)')
        );
        $this->assertEquals(
            ['name' => MysqlAdapter::PHINX_TYPE_BOOLEAN, 'limit' => null, 'precision' => null],
            $this->adapter->getPhinxType('tinyint(1)')
        );
    }

    public function testPhinxTypeNotValidType()
    {
        $this->setExpectedException('\RuntimeException', 'The type: "fake" is not supported.');
        $this->adapter->getPhinxType('fake');
    }

    public function testPhinxTypeNotValidTypeRegex()
    {
        $this->setExpectedException('\RuntimeException', 'Column type ?int? is not supported');
        $this->adapter->getPhinxType('?int?');
    }

    //index related tests

    public function testGetIndexSqlDefinitionRegular()
    {
        $index = $this->getMockBuilder('Phinx\Db\Table\Index')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getColumns', 'getName', 'getType'])
                      ->getMock();

        $index->expects($this->any())->method('getColumns')->will($this->returnValue(['column_name']));
        $index->expects($this->any())->method('getName')->will($this->returnValue('index_name'));
        $index->expects($this->any())->method('getType')->will($this->returnValue(\Phinx\Db\Table\Index::INDEX));
        $this->assertEquals(' KEY `index_name` (`column_name`)', $this->adapter->getIndexSqlDefinition($index));
    }

    public function testGetIndexSqlDefinitionUnique()
    {
        $index = $this->getMockBuilder('Phinx\Db\Table\Index')
                      ->disableOriginalConstructor()
                      ->setMethods([ 'getColumns', 'getName', 'getType'])
                      ->getMock();

        $index->expects($this->any())->method('getColumns')->will($this->returnValue(['column_name']));
        $index->expects($this->any())->method('getName')->will($this->returnValue('index_name'));
        $index->expects($this->any())->method('getType')->will($this->returnValue(\Phinx\Db\Table\Index::UNIQUE));
        $this->assertEquals(' UNIQUE KEY `index_name` (`column_name`)', $this->adapter->getIndexSqlDefinition($index));
    }

    public function testGetIndexesEmpty()
    {
        $this->result->expects($this->once())
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("SHOW INDEXES FROM `table_name`", $this->result);

        $indexes = $this->adapter->getIndexes("table_name");

        $this->assertEquals([], $indexes);
    }

    private function prepareCaseIndexes()
    {
        $index1 = [
            'Table' => 'table_name',
            'Non_unique' => '0',
            'Key_name' => 'PRIMARY',
            'Seq_in_index' => '1',
            'Column_name' => 'id',
            'Collation' => 'A',
            'Cardinality' => '0',
            'Sub_part' => 'NULL',
            'Packed' => 'NULL',
            'Null' => '',
            'Index_type' => 'BTREE',
            'Comment' => '',
            'Index_comment' => ''
        ];

        $index2 = [
            'Table' => 'table_name',
            'Non_unique' => '0',
            'Key_name' => 'index_name',
            'Seq_in_index' => '1',
            'Column_name' => 'column_name',
            'Collation' => 'A',
            'Cardinality' => '0',
            'Sub_part' => 'NULL',
            'Packed' => 'NULL',
            'Null' => '',
            'Index_type' => 'BTREE',
            'Comment' => '',
            'Index_comment' => ''
        ];

        $index3 = [
            'Table' => 'table_name',
            'Non_unique' => '0',
            'Key_name' => 'multiple_index_name',
            'Seq_in_index' => '1',
            'Column_name' => 'column_name',
            'Collation' => 'A',
            'Cardinality' => '0',
            'Sub_part' => 'NULL',
            'Packed' => 'NULL',
            'Null' => '',
            'Index_type' => 'BTREE',
            'Comment' => '',
            'Index_comment' => ''
        ];

        $index4 = [
            'Table' => 'table_name',
            'Non_unique' => '0',
            'Key_name' => 'multiple_index_name',
            'Seq_in_index' => '2',
            'Column_name' => 'another_column_name',
            'Collation' => 'A',
            'Cardinality' => '0',
            'Sub_part' => 'NULL',
            'Packed' => 'NULL',
            'Null' => '',
            'Index_type' => 'BTREE',
            'Comment' => '',
            'Index_comment' => ''
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($index1));

        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($index2));

        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue($index3));

        $this->result->expects($this->at(3))
                     ->method('fetch')
                     ->will($this->returnValue($index4));

        $this->result->expects($this->at(4))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $this->assertQuerySql("SHOW INDEXES FROM `table_name`", $this->result);

        return [$index1, $index2, $index3, $index4];
    }

    public function testGetIndexes()
    {
        list($index1, $index2, $index3, $index4) = $this->prepareCaseIndexes();
        $indexes = $this->adapter->getIndexes("table_name");

        $this->assertTrue(is_array($indexes));
        $this->assertEquals(3, count($indexes));
        $this->assertEquals(['columns' => [$index1['Column_name']]], $indexes[$index1['Key_name']]);
        $this->assertEquals(['columns' => [$index2['Column_name']]], $indexes[$index2['Key_name']]);
        $this->assertEquals(['columns' => [$index3['Column_name'], $index4['Column_name']]], $indexes[$index3['Key_name']]);
    }

    public function testHasIndexExistsAsString()
    {
        $this->prepareCaseIndexes();
        $this->assertTrue($this->adapter->hasIndex("table_name", "column_name"));
    }

    public function testHasIndexNotExistsAsString()
    {
        $this->prepareCaseIndexes();
        $this->assertFalse($this->adapter->hasIndex("table_name", "another_column_name"));
    }

    public function testHasIndexExistsAsArray()
    {
        $this->prepareCaseIndexes();
        $this->assertTrue($this->adapter->hasIndex("table_name", ["column_name"]));
    }

    public function testHasIndexNotExistsAsArray()
    {
        $this->prepareCaseIndexes();
        $this->assertFalse($this->adapter->hasIndex("table_name", ["another_column_name"]));
    }

    public function testAddIndex()
    {
        list($table, $index) = $this->prepareAddIndex(['getColumns']);

        $this->assertExecuteSql('ALTER TABLE `table_name` ADD  KEY (`column_name`)');
        $this->adapter->addIndex($table, $index);
    }

    public function testAddIndexWithLimit()
    {
        list($table, $index) = $this->prepareAddIndex(['getColumns', 'getLimit']);
        $index->expects($this->any())->method('getLimit')->will($this->returnValue(50));

        $this->assertExecuteSql('ALTER TABLE `table_name` ADD  KEY (`column_name`(50))');
        $this->adapter->addIndex($table, $index);
    }

    /**
     * @param array $methods
     * @return array
     */
    private function prepareAddIndex($methods)
    {
        $table = $this->getMockBuilder('Phinx\Db\Table')
            ->disableOriginalConstructor()
            ->setMethods(['getName'])
            ->getMock();
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));

        $index = $this->getMockBuilder('Phinx\Db\Table\Index')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        $index->expects($this->any())->method('getColumns')->will($this->returnValue(['column_name']));

        return [$table, $index];
    }

    public function testDropIndexAsString()
    {
        $this->prepareCaseIndexes();
        $this->assertExecuteSql('ALTER TABLE `table_name` DROP INDEX `index_name`');
        $this->adapter->dropIndex('table_name', 'column_name');
    }

    public function testDropIndexAsArray()
    {
        $this->prepareCaseIndexes();
        $this->assertExecuteSql('ALTER TABLE `table_name` DROP INDEX `index_name`');
        $this->adapter->dropIndex('table_name', ['column_name']);
    }

    public function testDropIndexByName()
    {
        $this->prepareCaseIndexes();
        $this->assertExecuteSql('ALTER TABLE `table_name` DROP INDEX `index_name`');
        $this->adapter->dropIndexByName('table_name', 'index_name');
    }

    //foregnkey related tests

    private function prepareCaseForeignKeys()
    {
        $fk = [
            'CONSTRAINT_NAME' => 'fk1',
            'TABLE_NAME' => 'table_name',
            'COLUMN_NAME' => 'other_table_id',
            'REFERENCED_TABLE_NAME' => 'other_table',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];

        $fk1 = [
            'CONSTRAINT_NAME' => 'fk2',
            'TABLE_NAME' => 'table_name',
            'COLUMN_NAME' => 'other_table_id',
            'REFERENCED_TABLE_NAME' => 'other_table',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];

        $fk2 = [
            'CONSTRAINT_NAME' => 'fk2',
            'TABLE_NAME' => 'table_name',
            'COLUMN_NAME' => 'another_table_id',
            'REFERENCED_TABLE_NAME' => 'other_table',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($fk));

        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue($fk1));

        $this->result->expects($this->at(2))
                     ->method('fetch')
                     ->will($this->returnValue($fk2));

        $this->result->expects($this->at(3))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $expectedSql = 'SELECT
              CONSTRAINT_NAME,
              TABLE_NAME,
              COLUMN_NAME,
              REFERENCED_TABLE_NAME,
              REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_NAME = \'table_name\'
            ORDER BY POSITION_IN_UNIQUE_CONSTRAINT';
        $this->assertQuerySql($expectedSql, $this->result);

        return [$fk, $fk1, $fk2];
    }

    public function testGetForeignKeys()
    {
        list($fk, $fk1, $fk2) = $this->prepareCaseForeignKeys();
        $foreignkeys = $this->adapter->getForeignKeys("table_name");

        $this->assertTrue(is_array($foreignkeys));
        $this->assertEquals(2, count($foreignkeys));
        $this->assertEquals('table_name', $foreignkeys['fk1']['table']);
        $this->assertEquals(['other_table_id'], $foreignkeys['fk1']['columns']);
        $this->assertEquals('other_table', $foreignkeys['fk1']['referenced_table']);
        $this->assertEquals(['id'], $foreignkeys['fk1']['referenced_columns']);
    }

    public function testHasForeignKeyExistsAsString()
    {
        $this->prepareCaseForeignKeys();
        $this->assertTrue($this->adapter->hasForeignKey("table_name", "other_table_id"));
    }

    public function testHasForeignKeyExistsAsStringAndConstraint()
    {
        $this->prepareCaseForeignKeys();
        $this->assertTrue($this->adapter->hasForeignKey("table_name", "other_table_id", 'fk1'));
    }

    public function testHasForeignKeyNotExistsAsString()
    {
        $this->prepareCaseForeignKeys();
        $this->assertFalse($this->adapter->hasForeignKey("table_name", "another_table_id"));
    }

    public function testHasForeignKeyNotExistsAsStringAndConstraint()
    {
        $this->prepareCaseForeignKeys();
        $this->assertFalse($this->adapter->hasForeignKey("table_name", "other_table_id", 'fk3'));
    }

    public function testHasForeignKeyExistsAsArray()
    {
        $this->prepareCaseForeignKeys();
        $this->assertTrue($this->adapter->hasForeignKey("table_name", ["other_table_id"]));
    }

    public function testHasForeignKeyExistsAsArrayAndConstraint()
    {
        $this->prepareCaseForeignKeys();
        $this->assertTrue($this->adapter->hasForeignKey("table_name", ["other_table_id"], 'fk1'));
    }

    public function testHasForeignKeyNotExistsAsArray()
    {
        $this->prepareCaseForeignKeys();
        $this->assertFalse($this->adapter->hasForeignKey("table_name", ["another_table_id"]));
    }

    public function testHasForeignKeyNotExistsAsArrayAndConstraint()
    {
        $this->prepareCaseForeignKeys();
        $this->assertFalse($this->adapter->hasForeignKey("table_name", ["other_table_id"], 'fk3'));
    }

    public function testAddForeignKeyBasic()
    {
        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName'])
                      ->getMock();
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));

        $refTable = $this->getMockBuilder('Phinx\Db\Table')
                         ->disableOriginalConstructor()
                         ->setMethods(['getName'])
                         ->getMock();
        $refTable->expects($this->any())->method('getName')->will($this->returnValue('other_table'));

        $foreignkey = $this->getMockBuilder('Phinx\Db\Table\ForeignKey')
                           ->disableOriginalConstructor()
                           ->setMethods([ 'getColumns',
                                               'getConstraint',
                                               'getReferencedColumns',
                                               'getOnDelete',
                                               'getOnUpdate',
                                               'getReferencedTable'])
                           ->getMock();

        $foreignkey->expects($this->any())->method('getColumns')->will($this->returnValue(['other_table_id']));
        $foreignkey->expects($this->any())->method('getConstraint')->will($this->returnValue('fk1'));
        $foreignkey->expects($this->any())->method('getReferencedColumns')->will($this->returnValue(['id']));
        $foreignkey->expects($this->any())->method('getReferencedTable')->will($this->returnValue($refTable));
        $foreignkey->expects($this->any())->method('getOnDelete')->will($this->returnValue(null));
        $foreignkey->expects($this->any())->method('getOnUpdate')->will($this->returnValue(null));

        $this->assertExecuteSql('ALTER TABLE `table_name` ADD  CONSTRAINT `fk1` FOREIGN KEY (`other_table_id`) REFERENCES `other_table` (`id`)');
        $this->adapter->addForeignKey($table, $foreignkey);
    }

    public function testAddForeignKeyComplete()
    {
        $table = $this->getMockBuilder('Phinx\Db\Table')
                      ->disableOriginalConstructor()
                      ->setMethods(['getName'])
                      ->getMock();
        $table->expects($this->any())->method('getName')->will($this->returnValue('table_name'));

        $refTable = $this->getMockBuilder('Phinx\Db\Table')
                         ->disableOriginalConstructor()
                         ->setMethods(['getName'])
                         ->getMock();
        $refTable->expects($this->any())->method('getName')->will($this->returnValue('other_table'));

        $foreignkey = $this->getMockBuilder('Phinx\Db\Table\ForeignKey')
                           ->disableOriginalConstructor()
                           ->setMethods([ 'getColumns',
                                               'getConstraint',
                                               'getReferencedColumns',
                                               'getOnDelete',
                                               'getOnUpdate',
                                               'getReferencedTable'])
                           ->getMock();

        $foreignkey->expects($this->any())->method('getColumns')->will($this->returnValue(['other_table_id']));
        $foreignkey->expects($this->any())->method('getConstraint')->will($this->returnValue('fk1'));
        $foreignkey->expects($this->any())->method('getReferencedColumns')->will($this->returnValue(['id']));
        $foreignkey->expects($this->any())->method('getReferencedTable')->will($this->returnValue($refTable));
        $foreignkey->expects($this->any())->method('getOnDelete')->will($this->returnValue('CASCADE'));
        $foreignkey->expects($this->any())->method('getOnUpdate')->will($this->returnValue('CASCADE'));

        $this->assertExecuteSql('ALTER TABLE `table_name` ADD  CONSTRAINT `fk1` FOREIGN KEY (`other_table_id`) REFERENCES `other_table` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->adapter->addForeignKey($table, $foreignkey);
    }

    public function testDropForeignKeyAsString()
    {
        $fk = [
            'CONSTRAINT_NAME' => 'fk1',
            'TABLE_NAME' => 'table_name',
            'COLUMN_NAME' => 'other_table_id',
            'REFERENCED_TABLE_NAME' => 'other_table',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($fk));

        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $expectedSql = 'SELECT
                        CONSTRAINT_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                        AND TABLE_NAME = \'table_name\'
                        AND COLUMN_NAME = \'column_name\'
                      ORDER BY POSITION_IN_UNIQUE_CONSTRAINT';
        $this->assertQuerySql($expectedSql, $this->result);

        $this->assertExecuteSql('ALTER TABLE `table_name` DROP FOREIGN KEY fk1');
        $this->adapter->dropForeignKey('table_name', 'column_name');
    }

    public function _testDropForeignKeyAsArray()
    {
        $fk = [
            'CONSTRAINT_NAME' => 'fk1',
            'TABLE_NAME' => 'table_name',
            'COLUMN_NAME' => 'other_table_id',
            'REFERENCED_TABLE_NAME' => 'other_table',
            'REFERENCED_COLUMN_NAME' => 'id'
        ];

        $this->result->expects($this->at(0))
                     ->method('fetch')
                     ->will($this->returnValue($fk));

        $this->result->expects($this->at(1))
                     ->method('fetch')
                     ->will($this->returnValue(null));

        $expectedSql = 'SELECT
                        CONSTRAINT_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                        AND TABLE_NAME = \'table_name\'
                        AND COLUMN_NAME = \'column_name\'
                      ORDER BY POSITION_IN_UNIQUE_CONSTRAINT';
        $this->assertQuerySql($expectedSql, $this->result);

        $this->assertExecuteSql('ALTER TABLE `table_name` DROP FOREIGN KEY fk1');
        $this->adapter->dropForeignKey('table_name', ['column_name']);
    }

    public function testDropForeignKeyAsStringByConstraint()
    {
        $this->assertExecuteSql('ALTER TABLE `table_name` DROP FOREIGN KEY fk1');
        $this->adapter->dropForeignKey('table_name', 'column_name', 'fk1');
    }

    public function _testDropForeignKeyAsArrayByConstraint()
    {
        $this->assertExecuteSql('ALTER TABLE `table_name` DROP FOREIGN KEY fk1');
        $this->adapter->dropForeignKey('table_name', ['column_name'], 'fk1');
    }
}
