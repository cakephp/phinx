<?php

namespace Phinx\Db\Action;

use InvalidArgumentException;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Table;

class AddForeignKey extends Action
{

    protected $table;

    protected $foreignKey;

    public function __construct(Table $table, ForeignKey $fk)
    {
        $this->table = $table;
        $this->foreignKey = $fk;
    }

    public static function build(Table $table, $columns, Table $referencedTable, $referencedColumns = ['id'], array $options = [])
    {
        if (is_string($referencedColumns)) {
            $referencedColumns = [$referencedColumns]; // str to array
        }

        $fk = new ForeignKey();
        $fk->setReferencedTable($referencedTable)
           ->setColumns($columns)
           ->setReferencedColumns($referencedColumns)
           ->setOptions($options);

        return new static($table, $fk);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}
