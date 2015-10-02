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
namespace Phinx\Db;

use Phinx\Db\Adapter\AdapterInterface;

/**
 *
 * A SeedTable is a table with an optional where clause that helps create
 * schema.sql from an existing database.
 */
class SeedTable
{
    /**
     * @var string
     */
    protected $where;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var array
     */
    private $seedData = null;

    /**
     * @var SeedTable[] 
     */
    private $dependencies=array();

    /**
     * Class Constuctor.
     *
     * @param string[] $seedinfo
     * @param string[] $options
     * @param AdapterInterface $adapter
     */
    public function __construct($seedinfo, $options = array(), AdapterInterface $adapter = null)
    {
        $this->options = $options;
        $this->adapter = $adapter;

        if (is_array($seedinfo)) {
            if (!isset($seedinfo['name'])) {
                throw new \RuntimeException("Seed configuration needs a table name. Problematic config: " . json_encode($seedinfo));
            }
            $table_name = $seedinfo['name'];

            if (isset($seedinfo['where'])) {
                $this->where = $seedinfo['where'];
            }
        } else {
            $table_name = $seedinfo;
        }

        $this->table = new Table($table_name, $options, $adapter);
    }

    /**
     * @return string where clause for the table
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string name of the seed table
     */
    public function getName()
    {
        return $this->table->getName();
    }

    /**
     * get the db adapter
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * associate an adapter with this seed
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter=$adapter;
        $this->table->setAdapter($adapter);
    }

    /**
     * tests whether the underlying table exists
     */
    public function exists()
    {
        if (!$this->getAdapter()) {
            throw new \RuntimeException("A database adapter needs to query the db");
        }
        return $this->getAdapter()->hasTable($this->getName());
    }

    /**
     * Tell Phinx that $this should be written after $seed
     * Return false if we detected a circular dependency
     *
     * @param SeedTable
     * @return void
     */
    public function setDependency(SeedTable $seed)
    {
        if (!isset($this->dependencies[$seed->getName()])) {
            $this->dependencies[$seed->getName()]=$seed;

            if ($this->dependsOn($this, true)) {
                unset($this->dependencies[$seed->getName()]);
                return false;
            }
        }
        return true;
    }

    /**
     * Remove a write dependency
     */
    public function unsetDependency(SeedTable $seed)
    {
        unset($this->dependencies[$seed->getName()]);
    }

    /**
     * @return SeedTable[]
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * Returns whether $this depends on $seed. 
     * Optionally traverses $this's dependency tree as well.
     *
     * @param SeedTable $seed  
     * @param boolena $recurse (default false) 
     */
    public function dependsOn(SeedTable $seed, $recurse=false)
    {
        if (isset($this->dependencies[$seed->getName()])) {
            return true;
        }
        if ($recurse) {
            foreach ($this->dependencies as $name=>$obj) {
                if ($obj->dependsOn($seed)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * get the insertSql necessary to insert the seed data
     */
    public function getInsertSql()
    {
        if (!$this->getSeedData()) {
            return;
        }
        
        $cols = array();
        foreach ($this->table->getColumns() as $col) {
            $cols[] = $col->getName();
        }
        $sql = $this->getAdapter()->getInsertSql($this->table, $cols, $this->getSeedData());
        // if getInsertSql returned a prepared statement to run a bunch of times, turn 
        // that into explicit inserts
        if (strpos($sql, '(?')) {
            $base = preg_replace('/\(\?(, *\?)*\)/', '', $sql);
            $sql = '';
            foreach ($this->getSeedData() as $row) {
                $sql .= $base . '(';
                if ($row[0] === null) {
                    $sql .= 'null';
                } else { 
                    $sql .= '\''.$row[0].'\'';
                }
                for ($i=1; $i<count($cols); $i++) {
                    if ($row[$i] === null) {
                        $sql .= ', null';
                    } else {
                        $sql .= ', \''.preg_replace("/'/", '\\\'', $row[$i]).'\'';
                    }
                }
                $sql .= ");\n";
            }
            return $sql;
        } else {
            return $sql.';';
        }
    }

    /**
     * get the data that will be used as seed data
     */
    private function getSeedData()
    {
        if ($this->seedData !== null) {
            return $this->seedData;
        }

        if (!$this->getAdapter()) {
            throw new \RuntimeException('SeedTable must have a DB adapter to get data');
        }

        // return false if the table doesn't exist
        $this->seedData=false;
        if ($this->getAdapter()->hasTable($this->getName())) {
            $rows = $this->getAdapter()->fetchAll($this->getQuery());
            foreach ($rows as $row) {
                $this->seedData[] = $row;
            }
        }
        return $this->seedData;
    }

    /**
     * get the Query used to dump the seed data
     */
    private function getQuery()
    {
        $sql = "select * from " . $this->getAdapter()->quoteTableName($this->table->getName());

        if ($this->where) {
            $sql .= " where " . $this->where;
        }

        return $sql;
    }
}
