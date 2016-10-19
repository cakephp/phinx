<?php
namespace Test\Phinx\Migration\Helper;
use Phinx\Db\Table;
use Phinx\Migration\Helper\CodeGenerator;
class CodeGeneratorTest extends \PHPUnit_Framework_TestCase
{
    public function testIsColumnSinglePrimaryKey()
    {
        $column = new Table\Column();
        $column->setName('name');
        $this->assertFalse(CodeGenerator::isColumnSinglePrimaryKey(new Table('dummy'), $column));
    }
    public function testIsColumnSinglePrimaryKeyId()
    {
        $column = new Table\Column();
        $column->setName('id');
        $this->assertTrue(CodeGenerator::isColumnSinglePrimaryKey(new Table('dummy'), $column));
    }
    public function testIsColumnSinglePrimaryKeyPk()
    {
        $column = new Table\Column();
        $column->setName('user_id');
        $this->assertTrue(CodeGenerator::isColumnSinglePrimaryKey(new Table('dummy', array('id'=>'user_id')), $column));
    }
    public function testBuildTableOptionsString()
    {
        $table = new Table('table', array('id'=>false, 'foreign_key'=>array('user_id', 'post_id')));
        $expected = "array('id'=>false, 'foreign_key'=>array('user_id', 'post_id'))";
        $this->assertEquals($expected, CodeGenerator::buildTableOptionsString($table));
    }
    public function testBuildColumnOptionsString()
    {
        $column = new Table\Column();
        $column->setName('user_id');
        $column->setType('integer');
        $column->setNull(true);
        $column->setLimit(4);
        $expected = "array('length'=>4,'null'=>true)";
        $this->assertEquals($expected, CodeGenerator::buildColumnOptionsString($column));
    }
    public function testBuildAddColumnArgumentsString()
    {
        $column = new Table\Column();
        $column->setName('user_id');
        $column->setType('integer');
        $column->setNull(true);
        $column->setLimit(4);
        $expected = "'user_id', 'integer', array('length'=>4,'null'=>true)";
        $this->assertEquals($expected, CodeGenerator::buildAddColumnArgumentsString($column));
    }
}