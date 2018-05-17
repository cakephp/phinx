<?php

use Phinx\Migration\AbstractMigration;

class TrickyEdgeCase extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('user_logins');
        $table
            ->rename('just_logins')
            ->addColumn('thingy', 'string', [
                'limit' => 12,
                'null' => true,
            ])
            ->addColumn('thingy2', 'integer')
            ->addIndex(['thingy'])
            ->save();
    }
}
