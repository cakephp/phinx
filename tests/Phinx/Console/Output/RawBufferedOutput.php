<?php
declare(strict_types=1);

namespace Test\Phinx\Console\Output;

use Symfony\Component\Console\Output\BufferedOutput;

/**
 * RawBufferedOutput is a specialized BufferedOutput that outputs raw "writeln" calls (ie. it doesn't replace the
 * tags like <info>message</info>.
 */
class RawBufferedOutput extends BufferedOutput
{
    /**
     * @param iterable|string $messages
     * @param int $options
     * @return void
     */
    public function writeln($messages, $options = 0): void
    {
        $this->write($messages, true, $options | self::OUTPUT_RAW);
    }
}
