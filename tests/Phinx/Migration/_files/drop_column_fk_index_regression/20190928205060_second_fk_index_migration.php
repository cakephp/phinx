<?php

use Phinx\Migration\AbstractMigration;

class SecondFkIndexMigration extends AbstractMigration
{
    public function up()
    {
        $this->table('table1', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '',
            'row_format' => 'DYNAMIC',
        ])
        ->removeColumn('table3_id')
        ->removeIndexByName('table1_table3_id')
        ->dropForeignKey('table3_id', 'table1_table3_id')
        ->update();
    }

    public function down()
    {
    }
}
