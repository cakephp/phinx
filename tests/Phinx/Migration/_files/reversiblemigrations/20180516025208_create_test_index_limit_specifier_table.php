<?php

use Phinx\Migration\AbstractMigration;

class CreateTestIndexLimitSpecifierTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('test_index_limit_specifier');
        $table->addColumn('column1', 'string')
              ->addColumn('column2', 'string')
              ->addColumn('column3', 'string')
              ->addIndex([ 'column1', 'column2', 'column3' ], [ 'limit' => [ 'column2' => 10 ] ])
              ->create();
    }
}
