<?php

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class DirectionAwareReversibleUp extends AbstractMigration
{
    public function change()
    {
        $this->table('change_direction_test')
            ->addColumn('thing', Column::STRING, [
                'limit' => 12,
            ])
            ->create();

        if ($this->isMigratingUp()) {
            $this->table('change_direction_test')->insert([
                [
                    'thing' => 'one',
                ],
                [
                    'thing' => 'two',
                ],
                [
                    'thing' => 'fox-socks',
                ],
                [
                    'thing' => 'mouse-box',
                ],
            ])->save();
        }
    }
}
