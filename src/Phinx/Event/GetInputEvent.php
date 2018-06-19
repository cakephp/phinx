<?php

namespace Phinx\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\Event;

class GetInputEvent extends Event
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * GetInputEvent constructor.
     *
     * @param InputInterface $input input arguments and options
     */
    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * Returns an input stream
     *
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }
}
