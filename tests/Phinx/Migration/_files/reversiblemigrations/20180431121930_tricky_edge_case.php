<?php

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class TrickyEdgeCase extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('user_logins');
        $table
            ->rename('just_logins')
            ->addColumn('thingy', Column::STRING, [
                'limit' => 12,
                'null' => true,
            ])
            ->addColumn('thingy2', Column::INTEGER)
            ->addIndex(['thingy'])
            ->save();
    }
}
