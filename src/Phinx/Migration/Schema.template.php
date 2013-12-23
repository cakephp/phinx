<?php use Phinx\Migration\Helper\CodeGeneratorHelper; ?>
<?php echo "<?php"; ?>


use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function up()
    {
<?php foreach ($tables as $table) : ?>
        $this->table('<?php echo $table->getName();?>', <?php echo CodeGeneratorHelper::buildTableOptionsString($table); ?>)
<?php $columns = $table->getColumns(); ?>
<?php foreach ($columns as $column) : ?>
<?php if (!CodeGeneratorHelper::isColumnSinglePrimaryKey($table, $column)) : ?>
            ->addColumn(<?php echo CodeGeneratorHelper::buildAddColumnArgumentsString($column);?>)
<?php endif; ?>
<?php endforeach; ?>
            ->save();

<?php endforeach; ?>
    }

    public function down()
    {
<?php foreach ($tables as $table) : ?>
        $this->dropTable('<?php echo $table->getName();?>');
<?php endforeach; ?>
    }
}