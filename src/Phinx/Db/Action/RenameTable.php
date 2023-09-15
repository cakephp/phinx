<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Action;

use Phinx\Db\Table\Table;

class RenameTable extends Action
{
    /**
     * The new name for the table
     *
     * @var string
     */
    protected string $newName;

    /**
     * Constructor
     *
     * @param \Phinx\Db\Table\Table $table The table to be renamed
     * @param string $newName The new name for the table
     */
    public function __construct(Table $table, string $newName)
    {
        parent::__construct($table);
        $this->newName = $newName;
    }

    /**
     * Return the new name for the table
     *
     * @return string
     */
    public function getNewName(): string
    {
        return $this->newName;
    }
}
