<?php

use Phinx\Migration\AbstractMigration;

class DirectionAwareReversibleUp extends AbstractMigration
{
    public function change()
    {
        $this->table('change_direction_test')
            ->addColumn('thing', 'string', [
                'limit' => 12,
            ])
            ->create();

        if ($this->isMigratingUp()) {
            $this->insert('change_direction_test', [
                [
                    'thing' => 'one',
                ],
                [
                    'thing' => 'two',
                ],
                [
                    'thing' => 'fox_socks',
                ],
                [
                    'thing' => 'mouse_box',
                ],
            ]);
        }
    }
}
