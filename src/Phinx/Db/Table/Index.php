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
 * @subpackage Phinx\Db
 */
namespace Phinx\Db\Table;

class Index
{
    /**
     * @var string
     */
    const UNIQUE = 'unique';
    
    /**
     * @var string
     */
    const INDEX = 'index';
    
    /**
     * @var array
     */
    protected $columns;
    
    /**
     * @var string
     */
    protected $type = self::INDEX;

    /**
     * @var string
     */
    protected $name = null;
    
    /**
     * Sets the index columns.
     *
     * @param array $columns
     * @return Column
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Gets the index columns.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }
    
    /**
     * Sets the index type.
     *
     * @param string $type
     * @return Index
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }
    
    /**
     * Gets the index type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * Utility method that maps an array of index options to this objects methods.
     *
     * @param array $options Options
     * @throws \RuntimeException
     * @return Index
     */
    public function setOptions($options)
    {
        // Valid Options
        $validOptions = array('type', 'unique', 'name');
        foreach ($options as $option => $value) {
            if (!in_array($option, $validOptions)) {
                throw new \RuntimeException('\'' . $option . '\' is not a valid index option.');
            }
            
            // handle $options['unique']
            if (strtolower($option) == self::UNIQUE) {
                if ((bool) $value) {
                    $this->setType(self::UNIQUE);
                }
                continue;
            }

            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }
    }
}
