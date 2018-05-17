<?php

namespace Baz;

use Phinx\Migration\AbstractMigration;

class RenameInfoTableToStatusesTable extends AbstractMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('info_baz');
        $table->rename('statuses_baz')->save();
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
