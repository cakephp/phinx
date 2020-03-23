<?php

use Phinx\Migration\AbstractMigration;

class ChangeColumnRegression extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('my_table');
        $table
            ->renameColumn('name', 'title')
            ->changeColumn('title', 'text')
            ->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
