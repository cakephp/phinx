<?php

use Phinx\Migration\AbstractMigration;

class UpdateInfoTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // info table
        $info = $this->table('info');
        $info->addColumn('password', 'string', array('limit' => 40))
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
