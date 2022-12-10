<?php

use Phinx\Migration\AbstractMigration;

class FirstFkIndexMigration extends AbstractMigration
{
    public function up()
    {
        $this->table('table2', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 20,
                'identity' => true,
            ])
            ->create();

        $this->table('table3', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 20,
                'identity' => true,
            ])
            ->create();

        $this->table('table1', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 20,
                'identity' => true,
            ])
            ->addColumn('table2_id', 'integer', [
                'null' => true,
                'limit' => 20,
                'after' => 'id',
            ])
            ->addIndex(['table2_id'], [
                    'name' => 'table1_table2_id',
                    'unique' => false,
            ])
            ->addForeignKey('table2_id', 'table2', 'id', [
                    'constraint' => 'table1_table2_id',
                    'update' => 'RESTRICT',
                    'delete' => 'RESTRICT',
            ])
            ->addColumn('table3_id', 'integer', [
                'null' => true,
                'limit' => 20,
            ])
            ->addIndex(['table3_id'], [
                'name' => 'table1_table3_id',
                'unique' => false,
            ])
            ->addForeignKey('table3_id', 'table3', 'id', [
                'constraint' => 'table1_table3_id',
                'update' => 'RESTRICT',
                'delete' => 'RESTRICT',
            ])
            ->create();
    }

    public function down()
    {
        $this->table('table1', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '',
            'row_format' => 'DYNAMIC',
        ])
        ->removeColumn('table2_id')
        ->removeIndexByName('table1_table2_id')
        ->dropForeignKey('table2_id', 'table1_table2_id')
        ->update();
    }
}
