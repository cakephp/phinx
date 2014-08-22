<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

use Phinx\Db\Table;
use Phinx\Db\Adapter\AdapterInterface;

/**
 * Abstract Migration Class. 
 *
 * It is expected that the migrations you write extend from this class.
 * 
 * This abstract class proxies the various database methods to your specified
 * adapter.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @var float
     */
    protected $version;
    
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Class Constructor.
     *
     * @param int $version Migration Version
     */
    final public function __construct($version)
    {
        $this->version = $version;
        $this->init();
    }
    
    /**
     * Initialize method.
     *
     * @return void
     */
    protected function init()
    {
    }
    
    /**
     * {@inheritdoc}
     */
    public function up()
    {
    }
    
    /**
     * {@inheritdoc}
     */
    public function down()
    {
    }
    
    /**
     * {@inheritdoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return get_class($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return $this->version;
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute($sql)
    {
        return $this->getAdapter()->execute($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        return $this->getAdapter()->query($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fetchRow($sql)
    {
        return $this->getAdapter()->fetchRow($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fetchAll($sql)
    {
        return $this->getAdapter()->fetchAll($sql);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options)
    {
        $this->getAdapter()->createDatabase($name, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function table($tableName, $options = array())
    {
        return new Table($tableName, $options, $this->getAdapter());
    }
    
    /**
     * A short-hand method to drop the given database table.
     *
     * @param string $tableName Table Name
     * @return void
     */
    public function dropTable($tableName)
    {
        $this->table($tableName)->drop();
    }
}
