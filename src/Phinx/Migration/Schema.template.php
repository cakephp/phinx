<?php echo "<?php"; ?>


use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function up()
    {
<?php foreach ($tables as $table) : ?>
        $this->table('`<?php echo $table->getName();?>`')
<?php $columns = $table->getColumns(); ?>
<?php foreach ($columns as $column) : ?>
            ->addColumn(<?php echo $this->buildAddColumnArgumentsString($column);?>)
<?php endforeach; ?>
            ->save();

<?php endforeach; ?>
    }

    public function down()
    {
<?php foreach ($tables as $table) : ?>
        $this->dropTable('`<?php echo $table->getName();?>`');
<?php endforeach; ?>
    }
}