<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class ChangePrimaryKey extends Action
{
    /**
     * The new columns for the primary key
     *
     * @var string|string[]|null
     */
    protected string|array|null $newColumns = null;

    /**
     * Constructor
     *
     * @param \Phinx\Db\Table\Table $table The table to be changed
     * @param string|string[]|null $newColumns The new columns for the primary key
     */
    public function __construct(Table $table, string|array|null $newColumns)
    {
        parent::__construct($table);
        $this->newColumns = $newColumns;
    }

    /**
     * Return the new columns for the primary key
     *
     * @return string|string[]|null
     */
    public function getNewColumns(): string|array|null
    {
        return $this->newColumns;
    }
}
