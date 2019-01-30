<?php
/**
 * Phinx
 *
 * (The MIT license)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Phinx\Db\Util;

/**
 * Contains all the information for running an ALTER command for a table,
 * and any post-steps required after the fact.
 */
class AlterInstructions
{

    /**
     * @var string[] The SQL snippets to be added to an ALTER instruction
     */
    protected $alterParts = [];

    /**
     * @var mixed[] The SQL commands to be executed after the ALTER instruction
     */
    protected $postSteps = [];

    /**
     * Constructor
     *
     * @param array $alterParts SQL snippets to be added to a single ALTER instruction per table
     * @param array $postSteps SQL commands to be executed after the ALTER instruction
     */
    public function __construct(array $alterParts = [], array $postSteps = [])
    {
        $this->alterParts = $alterParts;
        $this->postSteps = $postSteps;
    }

    /**
     * Adds another part to the single ALTER instruction
     *
     * @param string $part The SQL snipped to add as part of the ALTER instruction
     * @return void
     */
    public function addAlter($part)
    {
        $this->alterParts[] = $part;
    }

    /**
     * Adds a SQL command to be executed after the ALTER instruction.
     * This method allows a callable, with will get an empty array as state
     * for the first time and will pass the return value of the callable to
     * the next callable, if present.
     *
     * This allows to keep a single state across callbacks.
     *
     * @param string|callable $sql The SQL to run after, or a callable to execute
     * @return void
     */
    public function addPostStep($sql)
    {
        $this->postSteps[] = $sql;
    }

    /**
     * Returns the alter SQL snippets
     *
     * @return string[]
     */
    public function getAlterParts()
    {
        return $this->alterParts;
    }

    /**
     * Returns the SQL commands to run after the ALTER instruction
     *
     * @return mixed[]
     */
    public function getPostSteps()
    {
        return $this->postSteps;
    }

    /**
     * Merges another AlterInstructions object to this one
     *
     * @param AlterInstructions $other The other collection of instructions to merge in
     * @return void
     */
    public function merge(AlterInstructions $other)
    {
        $this->alterParts = array_merge($this->alterParts, $other->getAlterParts());
        $this->postSteps = array_merge($this->postSteps, $other->getPostSteps());
    }

    /**
     * Executes the ALTER instruction and all of the post steps.
     *
     * @param string $alterTemplate The template for the alter instruction
     * @param callable $executor The function to be used to execute all instructions
     * @return void
     */
    public function execute($alterTemplate, callable $executor)
    {
        if ($this->alterParts) {
            $alter = sprintf($alterTemplate, implode(', ', $this->alterParts));
            $executor($alter);
        }

        $state = [];

        foreach ($this->postSteps as $instruction) {
            if (is_callable($instruction)) {
                $state = $instruction($state);
                continue;
            }

            $executor($instruction);
        }
    }
}
