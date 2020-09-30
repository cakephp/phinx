<?php
declare(strict_types=1);

namespace Test\Phinx\Migration;

/**
 * RawBufferedOutput is a specialized BufferedOutput that outputs raw "writeln" calls (ie. it doesn't replace the
 * tags like <info>message</info>.
 */
class RawBufferedOutput extends \Symfony\Component\Console\Output\BufferedOutput
{
    public function writeln($messages, $options = self::OUTPUT_RAW)
    {
        $this->write($messages, true, $options);
    }
}
