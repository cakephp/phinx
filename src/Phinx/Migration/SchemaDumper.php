<?php

namespace Phinx\Migration;

use Phinx\Db\Adapter\AdapterInterface,
    Phinx\Db\Table\Column;

class SchemaDumper
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @param \Phinx\Db\Adapter\AdapterInterface $adapter
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return string with php code
     */
    public function dump()
    {
        $tables = $this->getAdapter()->getTables();
        if (!$tables) {

            return "";
        }

        ob_start();
        try {
            require_once dirname(__FILE__) . '/Schema.template.php';
            $dump = ob_get_contents();
        } catch (\Exception $e) {
            ob_end_clean();

            throw $e;
        }
        ob_end_clean();

        return $dump;
    }

    /**
     * Build options array for third argument in addColumn()
     *
     * @param Column $column
     *
     * @return string
     */
    protected function buildColumnOptionsString(Column $column)
    {
        $options = array('length', 'default', 'null', 'precision', 'scale', 'after', 'update', 'comment');
        $stringParts = array();
        foreach ($options as $option) {
            if ($option === 'length') {
                $method = 'getLimit';
            } else {
                $method = 'get' . ucfirst($option);
            }

            $value = $column->$method();
            if ($value === null) {

                continue;
            }
            if ($option === 'null' && $value === false) {

                continue;
            }

            if (is_numeric($value)) {
                $string = "'{$option}'=>$value";
            } elseif (is_bool($value)) {
                $string = $value ? "'{$option}'=>true" : "'{$option}'=>false";
            } else {
                $string = "'{$option}'=>'$value'";
            }
            $stringParts[] = $string;
        }

        return count($stringParts) > 0 ? "array(".implode(",", $stringParts).")" : "";
    }

    /**
     * Build arguments string for addColumn()
     *
     * @param Column $column
     *
     * @return string
     */
    protected function buildAddColumnArgumentsString(Column $column)
    {
        $args = array(
            "'`{$column->getName()}`'",
            "'{$column->getType()}'"
        );
        $options = $this->buildColumnOptionsString($column);
        if ($options) {
            $args[] = $options;
        }

        return implode(', ', $args);
    }
} 