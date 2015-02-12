<?php

use Phinx\Migration\AbstractMigration;

class RenameInfoTableToStatusesTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('info');
        $table->rename('statuses');
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
