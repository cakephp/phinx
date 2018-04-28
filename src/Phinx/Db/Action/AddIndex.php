<?php

namespace Phinx\Db\Action;

use InvalidArgumentException;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;

class AddIndex extends Action
{
    protected $index;

    public function __construct(Table $table, Index $index)
    {
        $this->table = $table;
        $this->index = $index;
    }

    public static function build(Table $table, $columns, array $options = [])
    {
        // create a new index object if strings or an array of strings were supplied
        $index = $columns;

        if (!$columns instanceof Index) {
            $index = new Index();

            if (is_string($columns)) {
                $columns = [$columns]; // str to array
            }

            $index->setColumns($columns);
            $index->setOptions($options);
        }

        return new static($table, $index);
    }

    public function getIndex()
    {
        return $this->index;
    }
}
