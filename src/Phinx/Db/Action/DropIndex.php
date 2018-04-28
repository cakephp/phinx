<?php

namespace Phinx\Db\Action;

use InvalidArgumentException;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;

class DropIndex extends Action
{

    protected $index;

    public function __construct(Table $table, Index $index)
    {
        $this->table = $table;
        $this->index = $index;
    }

    public static function build(Table $table, array $columns = [])
    {
        $index = new Index();
        $index->setColumns($columns);

        return new static($table, $index);
    }

    public static function buildFromName(Table $table, $name)
    {
        $index = new Index();
        $index->setName($name);

        return new static($table, $index);
    }

    public function getIndex()
    {
        return $this->index;
    }
}
