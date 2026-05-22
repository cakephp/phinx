<?php
declare(strict_types=1);

namespace Test\Phinx\Db\Util;

use Phinx\Db\Util\AlterInstructions;
use PHPUnit\Framework\TestCase;

class AlterInstructionsTest extends TestCase
{
    public function testPreStepsExecuteBeforeAlterParts()
    {
        $instructions = new AlterInstructions();
        $instructions->addPreStep('CREATE TYPE my_enum AS ENUM (\'a\', \'b\')');
        $instructions->addAlter('ADD my_col my_enum NOT NULL');

        $executed = [];
        $instructions->execute('ALTER TABLE t %s', function ($sql) use (&$executed) {
            $executed[] = $sql;
        });

        $this->assertCount(2, $executed);
        $this->assertEquals('CREATE TYPE my_enum AS ENUM (\'a\', \'b\')', $executed[0]);
        $this->assertEquals('ALTER TABLE t ADD my_col my_enum NOT NULL', $executed[1]);
    }

    public function testPreStepsWithCallable()
    {
        $instructions = new AlterInstructions();
        $called = false;
        $instructions->addPreStep(function () use (&$called) {
            $called = true;
        });
        $instructions->addAlter('ADD col INTEGER');

        $instructions->execute('ALTER TABLE t %s', function ($sql) {
        });

        $this->assertTrue($called, 'PreStep callable should have been invoked');
    }

    public function testGetPreSteps()
    {
        $instructions = new AlterInstructions();
        $this->assertEmpty($instructions->getPreSteps());

        $instructions->addPreStep('CREATE TYPE t AS ENUM (\'x\')');
        $this->assertCount(1, $instructions->getPreSteps());
    }

    public function testMergeIncludesPreSteps()
    {
        $a = new AlterInstructions();
        $a->addPreStep('CREATE TYPE a AS ENUM (\'1\')');
        $a->addAlter('ADD col_a a NOT NULL');

        $b = new AlterInstructions();
        $b->addPreStep('CREATE TYPE b AS ENUM (\'2\')');
        $b->addAlter('ADD col_b b NOT NULL');

        $a->merge($b);

        $this->assertCount(2, $a->getPreSteps());
        $this->assertCount(2, $a->getAlterParts());
    }

    public function testExecutionOrder()
    {
        $instructions = new AlterInstructions();
        $instructions->addPreStep('PRE1');
        $instructions->addPreStep('PRE2');
        $instructions->addAlter('ALTER_PART');
        $instructions->addPostStep('POST1');

        $executed = [];
        $instructions->execute('ALTER TABLE t %s', function ($sql) use (&$executed) {
            $executed[] = $sql;
        });

        $this->assertEquals(['PRE1', 'PRE2', 'ALTER TABLE t ALTER_PART', 'POST1'], $executed);
    }
}
