<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2012 Rob Morgan
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
namespace Phinx\Db\Table;

/**
 *
 * This object is based loosely on: http://api.rubyonrails.org/classes/ActiveRecord/ConnectionAdapters/Table.html.
 */
class Column
{
    /**
     * @var string
     */
    protected $name;
    
    /**
     * @var string
     */
    protected $type;
    
    /**
     * @var integer
     */
    protected $limit = null;
    
    /**
     * @var boolean
     */
    protected $null = false;
    
    /**
     * @var mixed
     */
    protected $default = null;
    
    /**
     * @var boolean
     */
    protected $identity = false;
    
    /**
     * @var string
     */
    protected $after;
    
    /**
     * Sets the column name.
     *
     * @param string $name
     * @return Column
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * Gets the column name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Sets the column type.
     *
     * @param string $type
     * @return Column
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }
    
    /**
     * Gets the column type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * Sets the column limit.
     *
     * @param string $limit
     * @return Column
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Gets the column limit.
     *
     * @return string
     */
    public function getLimit()
    {
        return $this->limit;
    }
    
    /**
     * Sets whether the column allows nulls.
     *
     * @param boolean $null
     * @return Column
     */
    public function setNull($null)
    {
        $this->null = (bool) $null;
        return $this;
    }
    
    /**
     * Gets whether the column allows nulls.
     *
     * @return boolean
     */
    public function getNull()
    {
        return $this->null;
    }
    
    /**
     * Does the column allow nulls?
     *
     * @return boolean
     */
    public function isNull()
    {
        return $this->getNull();
    }
    
    /**
     * Sets the default column value.
     *
     * @param mixed $default
     * @return Column
     */
    public function setDefault($default)
    {
        if ($default === false || $default === '') {
            $default = null;
        }
        $this->default = $default;
        return $this;
    }
    
    /**
     * Gets the default column value.
     *
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }
    
    /**
     * Sets whether or not the column is an identity column.
     *
     * @param boolean $identity
     * @return Column
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;
        return $this;
    }
    
    /**
     * Gets whether or not the column is an identity column.
     *
     * @return boolean
     */
    public function getIdentity()
    {
        return $this->identity;
    }
    
    /**
     * Is the column an identity column?
     *
     * @return boolean
     */
    public function isIdentity()
    {
        return $this->getIdentity();
    }
    
    /**
     * Sets the name of the column to add this column after.
     *
     * @param string $after After
     * @return Column
     */
    public function setAfter($after)
    {
        $this->after = $after;
        return $this;
    }
    
    /**
     * Returns the name of the column to add this column after.
     *
     * @return string
     */
    public function getAfter()
    {
        return $this->after;
    }
    
    /**
     * Utility method that maps an array of column options to this objects methods.
     *
     * @param array $options Options
     * @return Column
     */
    public function setOptions($options)
    {
        // Valid Options
        $validOptions = array('limit', 'length', 'default', 'null', 'precision', 'scale', 'after');
        foreach ($options as $option => $value) {
            if (!in_array($option, $validOptions)) {
                throw new \RuntimeException('\'' . $option . '\' is not a valid column option.');
            }
            
            // proxy length -> limit
            if (strtolower($option) == 'length') {
                $this->setLimit($value);
                continue;
            }
            
            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }
    }
}