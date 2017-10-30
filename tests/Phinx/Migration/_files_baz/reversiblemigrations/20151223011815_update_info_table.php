<?php

namespace Baz;

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class UpdateInfoTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // info table
        $info = $this->table('info_baz');
        $info->addColumn('password', Column::STRING, ['limit' => 40])
             ->update();
    }

    /**
     * Migrate Up.
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
