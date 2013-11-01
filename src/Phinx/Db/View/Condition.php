<?php

namespace Phinx\Db\View;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\View\Conditions\ConditionNode;


class Condition {

    private $parser;
     public function __construct() {
         $this->parser = new ConditionParser();
     }

    public function aand() {
        $this->parser->addCondition(new BinaryExpressionCondition("AND"));
        return $this;
    }

    public function oor() {
        $this->parser->addCondition(new BinaryExpressionCondition("OR"));
        return $this;
    }

    public function comparison($operation) {
        $this->parser->addCondition(new BinaryBooleanPrimaryCondition($operation));
        return $this;
    }

    public function column($column, $table = NULL) {
        $this->parser->addCondition(new IdentifierExpression($column, $table));
        return $this;
    }

    public function literal($literal)
    {
        $this->parser->addCondition(new LiteralExpression($literal));
        return $this;
    }

    public function null()
    {
        $this->parser->addCondition(new LiteralExpression("NULL"));
        return $this;
    }

    /**
     * @param boolean|string|null $value
     * @return $this
     */
    public function is($value)
    {
        //This would be better as a switch, but type coercion ruins it.
        if(is_null($value)) {
            return $this->isNull();
        } else if(is_string($value) && $value == "unknown") {
            return $this->isUnknown();
        } else if( is_bool($value) && $value == true) {
            return $this->isTrue();
        } else if (is_bool($value) && $value == false) {
            return $this->isFalse();
        }
        throw new \InvalidArgumentException("Unknown Is comparator");

    }

    public function isNot($value)
    {
        //This would be better as a switch, but type coercion ruins it.
        if(is_null($value)) {
            return $this->isNotNull();
        } else if(is_string($value) && $value == "unknown") {
            return $this->isNotUnknown();
        } else if( is_bool($value) && $value == true) {
            return $this->isNotTrue();
        } else if (is_bool($value) && $value == false) {
            return $this->isNotFalse();
        }
        throw new \InvalidArgumentException("Unknown Is Not comparator");

    }

    public function isNull() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS NULL"));
        return $this;
    }

    public function isTrue() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS TRUE"));
        return $this;
    }

    public function isFalse() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS FALSE"));
        return $this;
    }

    public function isUnknown() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS UNKNOWN"));
        return $this;
    }

    public function isNotNull() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS NOT NULL"));
        return $this;
    }

    public function isNotTrue() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS NOT TRUE"));
        return $this;
    }

    public function isNotFalse() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS NOT FALSE"));
        return $this;
    }

    public function isNotUnknown() {
        $this->parser->addCondition(new UnaryBooleanPrimaryCondition("IS NOT UNKNOWN"));
        return $this;
    }

    public function in()
    {
        $this->parser->addCondition(new BinaryPredicateCondition(("IN")));
        return $this;
    }
    public function getConditionSQL($adapater)
    {
        $topNode = $this->parser->getTopLevel();
        return  $topNode->createSQL($adapater);
    }


}


class ConditionParser {
    /**
     * @var \Phinx\Db\View\Conditions\ConditionNode[] stack
     */
    private $stack = array();

    /**
     * @var \Phinx\Db\View\Conditions\ConditionNode
     */
    private $topCondition = null;

    public function addCondition(Conditions\ConditionNode $condition) {
        if(count($this->stack) == 0) {
            $this->stack = array($condition);
            $this->topCondition = $condition;
        } else {
            $last = end($this->stack);
            $last->addOperand($condition);
            if($last->isComplete()) {
                array_pop($this->stack);
            }
            if(!$condition->isComplete()) {
                $this->stack[] = $condition;
            }


        }
    }

    public function getTopLevel() {
        return $this->topCondition;
    }
}

abstract class BinaryExpression implements ConditionNode
{

    private $type;

    /**
     * @var \Phinx\Db\View\Conditions\ConditionNode
     */
    public $leftCondition;

    /**
     * @var \Phinx\Db\View\Conditions\ConditionNode
     */
    public $rightCondition;

    /**
     * @var String
     */
    public $operation;

    /**
     * Generates the SQL that this condition uses
     * @param AdapterInterface $adapter The adapter to use for quoting and such
     * @return String
     */
    public function createSQL(AdapterInterface $adapter)
    {
        return $this->stringifyWithPrecedence($this->leftCondition, $adapter) . ' ' . $this->operation
                . ' ' . $this->stringifyWithPrecedence($this->rightCondition, $adapter);
    }

    private function stringifyWithPrecedence(ConditionNode $condition, $adapter) {
        if(get_class($condition) == get_class($this)) {
            return '(' . $condition->createSQL($adapter) . ')';
        }
        return $condition->createSQL($adapter);
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setOperation($operation)
    {
        $this->operation = $operation;
    }


    /**
     * Adds an operand to this condition
     * @param \Phinx\Db\View\Conditions\ConditionNode $operand The operand to add
     * @throws \InvalidArgumentException  If the operand isn't of type ExpressionCondition
     */
    public function addOperand(Conditions\ConditionNode $operand)
    {
        if ($operand instanceof $this->type) {
            if (isset($this->leftCondition)) {
                $this->rightCondition = $operand;
            } else {
                $this->leftCondition = $operand;
            }
        } else {
            throw new \InvalidArgumentException("Expected an expression of type " . $this->type . " recieved " . get_class($operand));
        }
    }

    /**
     * @return boolean
     */
    public function isComplete()
    {
        return isset($this->rightCondition);
    }

    public function __toString()
    {
        return get_class() . " " . $this->operation;
    }
}

abstract class PrefixUnaryExpression implements ConditionNode
{

    /**
     * @var String
     */
    public $operation;

    /**
     * @var \Phinx\Db\View\Conditions\ConditionNode
     */
    public $condition;


    function setOperation($operation)
    {
        $this->operation = $operation;
    }

    function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Generates the SQL that this condition uses
     * @param AdapterInterface $adapter The adapter to use for quoting and such
     * @return String
     */
    public function createSQL(AdapterInterface $adapter)
    {
        return $this->operation . ' ' . $this->condition->createSQL($adapter);
    }

    /**
     * Adds an operand to this condition
     * @param \Phinx\Db\View\Conditions\ConditionNode $operand The operand to add
     */
    public function addOperand(Conditions\ConditionNode $operand)
    {
        $this->condition = $operand;
    }

    /**
     * @return boolean
     */
    public function isComplete()
    {
        return isset($this->condition);
    }

    public function __toString()
    {
        return get_class() . " " . $this->operation;
    }
}

abstract class PostfixUnaryExpression extends PrefixUnaryExpression {
    /**
     * Generates the SQL that this condition uses
     * @param AdapterInterface $adapter The adapter to use for quoting and such
     * @return String
     */
    public function createSQL(AdapterInterface $adapter)
    {
        return $this->condition->createSQL($adapter) . ' ' .  $this->operation;
    }
}



abstract class NullaryExpression implements ConditionNode {
    /**
     * Adds an operand to this condition
     * @param \Phinx\Db\View\Conditions\ConditionNode $operand The operand to add
     * @throws \Exception when called
     */
    public function addOperand(Conditions\ConditionNode $operand)
    {
        throw new \Exception("Cannot add an operand to a simple expression");
    }

    /**
     * @return boolean
     */
    public function isComplete()
    {
        return true;
    }
}


interface ExpressionCondition extends Conditions\ConditionNode {

}

interface BooleanPrimaryCondition extends ExpressionCondition
{


}

interface PredicateCondition extends BooleanPrimaryCondition {

}

interface BitExprCondition extends PredicateCondition
{

}

interface SimpleExprCondition extends BitExprCondition {


}


class BinaryBitExprCondition extends BinaryExpression implements  BitExprCondition
{

    public function __construct($operation)
    {
        $this->setType("Phinx\\Db\\View\\BitExprCondition");
        $this->setOperation($operation);
    }
}

class BinaryBooleanPrimaryCondition extends BinaryExpression implements  BooleanPrimaryCondition
{

    public function __construct($operation)
    {
        $this->setType("Phinx\\Db\\View\\BooleanPrimaryCondition");
        $this->setOperation($operation);
    }
}

class UnaryBooleanPrimaryCondition extends PostfixUnaryExpression implements  BooleanPrimaryCondition
{

    public function __construct($operation)
    {
        $this->setType("Phinx\\Db\\View\\BooleanPrimaryCondition");
        $this->setOperation($operation);
    }
}

class BinaryExpressionCondition extends BinaryExpression implements  ExpressionCondition
{

    public function __construct($operation)
    {
        $this->setType("Phinx\\Db\\View\\ExpressionCondition");
        $this->setOperation($operation);
    }
}

class BinaryPredicateCondition extends BinaryExpression implements PredicateCondition
{
    public function __construct($operation)
    {
        $this->setType("Phinx\\Db\\View\\BitExprCondition");
        $this->setOperation($operation);
    }

}





class UnaryExpressionCondition extends PrefixUnaryExpression implements  ExpressionCondition {

    public function __construct($operation) {
        $this->setType("Phinx\\Db\\View\\ExpressionCondition");
        $this->setOperation($operation);
    }
}




class IdentifierExpression extends NullaryExpression implements SimpleExprCondition {

    public $table;
    public $column;

    function __construct($column, $table = null)
    {
        $this->column = $column;
        $this->table = $table;
    }

    public function createSQL(AdapterInterface $adapter) {
        if (!is_null($this->table)) {
            return $adapter->quoteTableName($this->table) . '.' . $adapter->quoteColumnName($this->column);
        } else {
            return $adapter->quoteColumnName($this->column);
        }

    }

    public function __toString() {
        if(is_null($this->table)) {
            return get_class() . " " . $this->column;
        }
        return get_class() . " " . $this->table . "." . $this->column;
    }

}

class LiteralExpression extends NullaryExpression implements  SimpleExprCondition {

    public $literal;

    public function __construct($literal) {
        $this->literal = $literal;
    }

    public function createSQL(AdapterInterface $adapter) {
        if(is_string($this->literal) ) {
            return "'" . $this->literal . "'";
        } else if (is_array($this->literal)) {
            $r_arr = array();
            foreach($this->literal as $value) {
                if(is_string($value)) {
                    $r_arr[] = "'". $value . "'";
                } else {
                    $r_arr[] = $value;
                }
            }
            return "(" . implode(",", $r_arr) . ")";
        } else {
            return $this->literal;
        }
    }
}