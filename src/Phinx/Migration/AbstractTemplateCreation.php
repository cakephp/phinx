<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Migration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTemplateCreation implements CreationInterface
{
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    public function __construct(InputInterface $input = null, OutputInterface $output = null)
    {
        if ($input !== null) {
            $this->setInput($input);
        }
        if ($output !== null) {
            $this->setOutput($output);
        }
    }

    /**
     * @inheritDoc
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }
}
