<?php
/**
 * Created by PhpStorm.
 * User: Daniel
 * Date: 10/22/13
 * Time: 8:55 PM
 */
namespace Phinx\Db\View\Conditions;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\View\Conditions;

interface ConditionNode
{

    /**
     * Generates the SQL that this condition uses
     * @param AdapterInterface $adapter The adapter to use for quoting and such
     * @return String
     */
    public function createSQL(AdapterInterface $adapter);

    /**
     * Adds an operand to this condition
     * @param ConditionNode $operand The operand to add
     */
    public function addOperand(Conditions\ConditionNode $operand);

    /**
     * @return boolean
     */
    public function isComplete();

}