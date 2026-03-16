<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Db\Util;

use InvalidArgumentException;

/**
 * Contains all the information for running an ALTER command for a table,
 * and any post-steps required after the fact.
 */
class AlterInstructions
{
    /**
     * @var string[] The SQL snippets to be added to an ALTER instruction
     */
    protected array $alterParts = [];

    /**
     * @var (string|callable)[] The SQL commands to be executed after the ALTER instruction
     */
    protected array $postSteps = [];

    /**
     * @var string|null MySQL-specific: ALGORITHM clause
     */
    protected ?string $algorithm = null;

    /**
     * @var string|null MySQL-specific: LOCK clause
     */
    protected ?string $lock = null;

    /**
     * Constructor
     *
     * @param string[] $alterParts SQL snippets to be added to a single ALTER instruction per table
     * @param (string|callable)[] $postSteps SQL commands to be executed after the ALTER instruction
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
    public function addAlter(string $part): void
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
    public function addPostStep(string|callable $sql): void
    {
        $this->postSteps[] = $sql;
    }

    /**
     * Returns the alter SQL snippets
     *
     * @return string[]
     */
    public function getAlterParts(): array
    {
        return $this->alterParts;
    }

    /**
     * Returns the SQL commands to run after the ALTER instruction
     *
     * @return (string|callable)[]
     */
    public function getPostSteps(): array
    {
        return $this->postSteps;
    }

    /**
     * Sets the ALGORITHM clause (MySQL-specific)
     *
     * @param string|null $algorithm The algorithm to use
     * @return void
     */
    public function setAlgorithm(?string $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    /**
     * Gets the ALGORITHM clause (MySQL-specific)
     *
     * @return string|null
     */
    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    /**
     * Sets the LOCK clause (MySQL-specific)
     *
     * @param string|null $lock The lock mode to use
     * @return void
     */
    public function setLock(?string $lock): void
    {
        $this->lock = $lock;
    }

    /**
     * Gets the LOCK clause (MySQL-specific)
     *
     * @return string|null
     */
    public function getLock(): ?string
    {
        return $this->lock;
    }

    /**
     * Merges another AlterInstructions object to this one
     *
     * @param \Phinx\Db\Util\AlterInstructions $other The other collection of instructions to merge in
     * @throws \InvalidArgumentException When algorithm or lock specifications conflict
     * @return void
     */
    public function merge(AlterInstructions $other): void
    {
        $this->alterParts = array_merge($this->alterParts, $other->getAlterParts());
        $this->postSteps = array_merge($this->postSteps, $other->getPostSteps());

        if ($other->getAlgorithm() !== null) {
            if ($this->algorithm !== null && $this->algorithm !== $other->getAlgorithm()) {
                throw new InvalidArgumentException(sprintf(
                    'Conflicting algorithm specifications in batched operations: "%s" and "%s". ' .
                    'All operations in a batch must use the same algorithm, or specify it on only one operation.',
                    $this->algorithm,
                    $other->getAlgorithm(),
                ));
            }
            $this->algorithm = $other->getAlgorithm();
        }
        if ($other->getLock() !== null) {
            if ($this->lock !== null && $this->lock !== $other->getLock()) {
                throw new InvalidArgumentException(sprintf(
                    'Conflicting lock specifications in batched operations: "%s" and "%s". ' .
                    'All operations in a batch must use the same lock mode, or specify it on only one operation.',
                    $this->lock,
                    $other->getLock(),
                ));
            }
            $this->lock = $other->getLock();
        }
    }

    /**
     * Executes the ALTER instruction and all of the post steps.
     *
     * @param string $alterTemplate The template for the alter instruction
     * @param callable $executor The function to be used to execute all instructions
     * @return void
     */
    public function execute(string $alterTemplate, callable $executor): void
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
