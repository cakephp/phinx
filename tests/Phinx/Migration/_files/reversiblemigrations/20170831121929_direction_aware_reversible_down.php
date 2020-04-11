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
            $this->execute("UPDATE change_direction_test
                SET subthing = SUBSTRING(thing, LOCATE('_', thing) + 1),
                    thing = LEFT(thing, LOCATE('_', thing) - 1)
                WHERE thing LIKE '%\\\\_%'");
        } else {
            $this->execute("UPDATE change_direction_test
                SET thing = CONCAT_WS('_', thing, subthing)");
        }
    }
}
