<?php use Phinx\Migration\Helper\CodeGenerator; ?>
<?php echo "<?php"; ?>

use Phinx\Migration\AbstractMigration;

class Schema extends AbstractMigration
{
    public function change()
    {
    <?php foreach ($tables as $table) : ?>
        $this->table('<?php echo $table->getName();?>', <?php echo CodeGenerator::buildTableOptionsString($table); ?>)
        <?php $columns = $table->getColumns(); ?>
        <?php $foreignKeys = $table->getAdapter()->getForeignKeys($table->getName()); ?>
        <?php $indexes = $table->getAdapter()->getIndexes($table->getName()); ?>
        <?php foreach ($columns as $column) : ?>
            <?php if (!CodeGenerator::isColumnSinglePrimaryKey($table, $column)) : ?>
                ->addColumn(<?php echo CodeGenerator::buildAddColumnArgumentsString($column);?>)
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (count($foreignKeys) > 0) : ?>
            <?php foreach ($foreignKeys as $foreignKey) : ?>
                <?php echo CodeGenerator::buildFkString($foreignKey); ?>
                
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (count($indexes) > 0) : ?>
            <?php foreach ($indexes as $key => $index) : ?>
                ->addIndex(array('<?php echo implode("', '", $index['columns']);?>'), array('unique' => <?php echo $index['unique'] ? 'true' : 'false'; ?>, 'name' => '<?php echo $key; ?>'))
            <?php endforeach; ?>
        <?php endif; ?>
        ->create();

    <?php endforeach; ?>
    <?php foreach ($tables as $table) : ?>
        <?php
//        $foreignKeys = $table->getAdapter()->getForeignKeys($table->getName());
        $foreignKeys = $table->getForeignKeys();
        if (count($foreignKeys) > 0) : ?>
            $this->table('<?php echo $table->getName();?>', <?php echo CodeGenerator::buildTableOptionsString($table); ?>)
            <?php foreach ($foreignKeys as $foreignKey) : ?>
                <?php echo CodeGenerator::buildFkString($foreignKey); ?>

            <?php endforeach; ?>
            ->update();

        <?php endif; ?>
    <?php endforeach; ?>
    }
}
