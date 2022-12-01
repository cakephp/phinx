<?php

use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Migration\AbstractMigration;

class AddColumnIndexFk extends AbstractMigration
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
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('statuses')
            ->addColumn('user_id', Column::INTEGER, [
                'null' => true,
                'limit' => 20,
                'signed' => false,
            ])
            ->addIndex(['user_id'], [
                'name' => 'statuses_users_id',
                'unique' => false,
            ]);

        if ($this->getAdapter()->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $table->addForeignKey('user_id', 'users', 'id', [
                'update' => ForeignKey::NO_ACTION,
                'delete' => ForeignKey::NO_ACTION,
            ]);
        } else {
            $table->addForeignKey('user_id', 'users', 'id', [
                'constraint' => 'statuses_users_id',
                'update' => ForeignKey::NO_ACTION,
                'delete' => ForeignKey::NO_ACTION,
            ]);
        }

        $table->update();
    }
}
