<?php

namespace Test\Phinx\Db\Table;

use Phinx\Db\Table\Column;

class ColumnTest extends \PHPUnit_Framework_TestCase
{
	private static $O;

	public static function setUpBeforeClass()
	{
		self::$O = new \Phinx\Db\Table\Column();
	}

	public function testSetName()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setName(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setName('my_name'));
		$this->assertSame('my_name', $data->getName());
	}

	public function testGetName()
	{
		// smoke test
		self::$O->setName(null);
		$this->assertEmpty($data = self::$O->getName());
		// true case
		$data = self::$O->setName('my_name');
		$this->assertSame('my_name', $data->getName());
		$this->assertSame('my_name', self::$O->getName());
	}

	public function testSetType()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setType(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setType('my_type'));
		$this->assertSame('my_type', $data->getType());
	}

	public function testGetType()
	{
		// smoke test
		self::$O->setType(null);
		$this->assertEmpty($data = self::$O->getType());
		// true case
		$data = self::$O->setType('my_type');
		$this->assertSame('my_type', $data->getType());
		$this->assertSame('my_type', self::$O->getType());
	}

	public function testSetLimit()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setLimit(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setLimit('my_limit'));
		$this->assertSame('my_limit', $data->getLimit());
	}

	public function testGetLimit()
	{
		// smoke test
		self::$O->setLimit(null);
		$this->assertEmpty($data = self::$O->getLimit());
		// true case
		$data = self::$O->setLimit('my_limit');
		$this->assertSame('my_limit', $data->getLimit());
		$this->assertSame('my_limit', self::$O->getLimit());
	}

	public function testSetNull()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setNull(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setNull('my_limit'));
		$this->assertTrue($data->getNull());
		// set false
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setNull(false));
		$this->assertFalse($data->getNull());
	}

	public function testGetNull()
	{
		// smoke test
		self::$O->setNull(null);
		$this->assertEmpty($data = self::$O->getNull());
		// true case
		$data = self::$O->setNull('my_null');
		$this->assertTrue($data->getNull());
		$this->assertTrue(self::$O->getNull());
	}

	public function testIsNull()
	{
		// smoke test
		self::$O->setNull(null);
		$this->assertFalse(self::$O->isNull());
		// true case
		$data = self::$O->setNull(1);
		$this->assertTrue($data->isNull());
		$this->assertTrue(self::$O->isNull());
	}

	public function testSetDefault()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setDefault(null));
		$this->assertNull($data->getDefault());
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setDefault('my_default'));
		$this->assertSame('my_default', $data->getDefault());
		// set empty value
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setDefault(''));
		$this->assertSame('', $data->getDefault());
	}

	public function testGetDefault()
	{
		// smoke test
		self::$O->setDefault(null);
		$this->assertEmpty($data = self::$O->getDefault());
		// true case
		$data = self::$O->setDefault('my_default');
		$this->assertSame('my_default', $data->getDefault());
		$this->assertSame('my_default', self::$O->getDefault());
	}

	public function testSetIdentity()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setIdentity(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setIdentity('my_identity'));
		$this->assertSame('my_identity', $data->getIdentity());
	}

	public function testGetIdentity()
	{
		// smoke test
		self::$O->setIdentity(null);
		$this->assertEmpty($data = self::$O->getIdentity());
		// true case
		$data = self::$O->setIdentity('my_identity');
		$this->assertSame('my_identity', $data->getIdentity());
		$this->assertSame('my_identity', self::$O->getIdentity());
	}

	public function testIsIdentity()
	{
		// smoke test
		self::$O->setIdentity(null);
		$this->assertNull(self::$O->isIdentity());
		// true case
		$data = self::$O->setIdentity(1);
		$this->assertSame(1, $data->isIdentity());
		$this->assertSame(1, self::$O->isIdentity());
	}

	public function testSetAfter()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setAfter(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setAfter('my_after'));
		$this->assertSame('my_after', $data->getAfter());
	}

	public function testGetAfter()
	{
		// smoke test
		self::$O->setAfter(null);
		$this->assertEmpty($data = self::$O->getAfter());
		// true case
		$data = self::$O->setAfter('my_after');
		$this->assertSame('my_after', $data->getAfter());
		$this->assertSame('my_after', self::$O->getAfter());
	}

	public function testSetUpdate()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setUpdate(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setUpdate('my_update'));
		$this->assertSame('my_update', $data->getUpdate());
	}

	public function testGetUpdate()
	{
		// smoke test
		self::$O->setUpdate(null);
		$this->assertEmpty($data = self::$O->getUpdate());
		// true case
		$data = self::$O->setUpdate('my_update');
		$this->assertSame('my_update', $data->getUpdate());
		$this->assertSame('my_update', self::$O->getUpdate());
	}

	public function testSetPrecision()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setPrecision(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setPrecision('my_precision'));
		$this->assertSame('my_precision', $data->getPrecision());
	}

	public function testGetPrecision()
	{
		// smoke test
		self::$O->setPrecision(null);
		$this->assertEmpty($data = self::$O->getPrecision());
		// true case
		$data = self::$O->setPrecision('my_precision');
		$this->assertSame('my_precision', $data->getPrecision());
		$this->assertSame('my_precision', self::$O->getPrecision());
	}

	public function testSetScale()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setScale(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setScale('my_scale'));
		$this->assertSame('my_scale', $data->getScale());
	}

	public function testGetScale()
	{
		// smoke test
		self::$O->setScale(null);
		$this->assertEmpty($data = self::$O->getScale());
		// true case
		$data = self::$O->setScale('my_scale');
		$this->assertSame('my_scale', $data->getScale());
		$this->assertSame('my_scale', self::$O->getScale());
	}

	public function testSetComment()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setComment(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setComment('my_comment'));
		$this->assertSame('my_comment', $data->getComment());
	}

	public function testGetComment()
	{
		// smoke test
		self::$O->setComment(null);
		$this->assertEmpty($data = self::$O->getComment());
		// true case
		$data = self::$O->setComment('my_comment');
		$this->assertSame('my_comment', $data->getComment());
		$this->assertSame('my_comment', self::$O->getComment());
	}

	public function testSetSigned()
	{
		// smoke test
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setSigned(null));
		// true case
		$this->assertInstanceOf('\Phinx\Db\Table\Column', $data = self::$O->setSigned(1));
		$this->assertTrue($data->getSigned());
	}

	public function testGetSigned()
	{
		// smoke test
		self::$O->setSigned(null);
		$this->assertEmpty($data = self::$O->getSigned());
		// true case
		$data = self::$O->setSigned(1);
		$this->assertTrue($data->getSigned());
		$this->assertTrue(self::$O->getSigned());
	}

	public function testIsSigned()
	{
		// smoke test
		self::$O->setSigned(null);
		$this->assertFalse(self::$O->isSigned());
		// true case
		$data = self::$O->setSigned(1);
		$this->assertTrue($data->isSigned());
		$this->assertTrue(self::$O->isSigned());
	}

	public function testSetOptions()
	{
		// smoke
		$this->assertEmpty(self::$O->setOptions([]));
		// false case - invalid option
		try {
			$this->assertEmpty(self::$O->setOptions(['test_option_value']));
		} catch (\RuntimeException $e) {
			$this->assertInstanceOf( 'RuntimeException', $e, 'Expected exception of type RuntimeException, got ' . get_class($e) );
			$this->assertSame("'0' is not a valid column option.", $e->getMessage());
		}
		// true - valid option
		$this->assertEmpty(self::$O->setOptions(['comment' => 'test_option_value']));
		$this->assertSame('test_option_value', self::$O->getComment());
		// true - valid option - used proxy method
		$this->assertEmpty(self::$O->setOptions(['values' => 'test_values']));
		$this->assertSame('test_values', self::$O->getLimit());
	}
}