<?php

namespace Phinx\Db\Adapter;

use Phinx\Migration\MigrationInterface;

class ExportAdapter extends AdapterWrapper
{
    /**
     * @inheritdoc
     */
    public function execute($sql)
    {
        print $sql . PHP_EOL;
    }

    /**
     * @inheritdoc
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasTransactions()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getAdapterType()
    {
        return 'ExportAdapter';
    }
}
