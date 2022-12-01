<?php

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class DirectionAwareReversibleDown extends AbstractMigration
{
    public function change()
    {
        $this->table('change_direction_test')
            ->addColumn('subthing', Column::STRING, [
                'limit' => 12,
                'null' => true,
            ])
            ->update();

        if ($this->isMigratingUp()) {
            $query = $this->getQueryBuilder();
            $query
                ->update('change_direction_test')
                ->set(['subthing' => $query->identifier('thing')])
                ->where(['thing LIKE' => '%-%'])
                ->execute();
        } else {
            $this
                ->getQueryBuilder()
                ->update('change_direction_test')
                ->set(['subthing' => null])
                ->execute();
        }
    }
}
