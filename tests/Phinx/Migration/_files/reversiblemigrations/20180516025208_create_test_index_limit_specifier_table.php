<?php

use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class CreateTestIndexLimitSpecifierTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
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
        $table->addColumn('column1', Column::STRING)
              ->addColumn('column2', Column::STRING)
              ->addColumn('column3', Column::STRING)
              ->addIndex([ 'column1', 'column2', 'column3' ], [ 'limit' => [ 'column2' => 10 ] ])
              ->create();
    }
}
