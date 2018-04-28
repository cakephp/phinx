<?php

namespace Phinx\Db\Action;

use InvalidArgumentException;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Table;

class DropForeignKey extends Action
{

    protected $foreignKey;

    public function __construct(Table $table, ForeignKey $foreignKey)
    {
        $this->table = $table;
        $this->foreignKey = $foreignKey;
    }

    public static function build(Table $table, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $foreignKey = new ForeignKey();
        $foreignKey->setColumns($columns);

        if ($constraint) {
            $foreignKey->setConstraint($constraint);
        }

        return new static($table, $foreignKey);
    }

    public function getForeignKey()
    {
        return $this->foreignKey;
    }
}
