<?php

namespace Test\Phinx\Console\Output;

/**
 * RawBufferedOutput is a specialized BufferedOutput that outputs raw "writeln" calls (ie. it doesn't replace the
 * tags like <info>message</info>.
 */
class RawBufferedOutput extends \Symfony\Component\Console\Output\BufferedOutput
{
    /**
     * @param iterable|string $messages
     * @param int $options
     * @return void
     */
    public function writeln($messages, $options = self::OUTPUT_RAW)
    {
        $this->write($messages, true, $options);
    }
}
