<?php use Phinx\Migration\Helper\CodeGenerator; ?>
<?php echo "<?php"; ?>


use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function up()
    {
<?php foreach ($tables as $table) : ?>
        $this->table('<?php echo $table->getName();?>', <?php echo CodeGenerator::buildTableOptionsString($table); ?>)
<?php $columns = $table->getColumns(); ?>
<?php foreach ($columns as $column) : ?>
<?php if (!CodeGenerator::isColumnSinglePrimaryKey($table, $column)) : ?>
            ->addColumn(<?php echo CodeGenerator::buildAddColumnArgumentsString($column);?>)
<?php endif; ?>
<?php endforeach; ?>
<?php
    $foreignKeys = $table->getAdapter()->getForeignKeys($table->getName());
    if (count($foreignKeys) > 0) : ?>
<?php foreach ($foreignKeys as $foreignKey) : ?>
            <?php echo CodeGenerator::buildFkString($foreignKey); ?>

<?php endforeach; ?>
<?php endif; ?>
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