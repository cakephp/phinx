<?php
/* Phinx
 *
 * (The MIT license)
 * Copyright (c) 2014 Rob Morgan
 * Copyright (c) 2014 Woody Gilk
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

namespace Phinx\Wrapper;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Phinx text wrapper: a way to run `status`, `migrate`, and `rollback` commands
 * and get the output of the command back as plain text.
 *
 * @author Woody Gilk <woody.gilk@gmail.com>
 */
class TextWrapper
{
    private $app;
    private $env;
    private $exit_code;

    /**
     * @param  Phinx\Console\PhinxApplication $app
     * @param  String                         $default_env  environment fallback, defaults to "development"
     * @return void
     */
    public function __construct(PhinxApplication $app, $default_env = 'development')
    {
        $this->app = $app;
        $this->env = $default_env;
    }

    /**
     * Returns the exit code from the last run command.
     * @return Integer
     */
    public function getExitCode()
    {
        return $this->exit_code;
    }

    /**
     * Returns the output from running the "status" command.
     * @param  String  $env  environment name (optional)
     * @return String
     */
    public function getStatus($env = null)
    {
        $command = ['status', '-e' => $env ?: $this->env];
        return $this->executeRun($command);
    }

    /**
     * Returns the output from running the "migrate" command.
     * @param  String  $env     environment name (optional)
     * @param  String  $target  target version (optional)
     * @return String
     */
    public function getMigrate($env = null, $target = null)
    {
        $command = ['migrate', '-e' => $env ?: $this->env];
        if ($target) {
            $command += ['-t' => $target];
        }
        return $this->executeRun($command);
    }

    /**
     * Returns the output from running the "rollback" command.
     * @param  String  $env     environment name (optional)
     * @param  Mixed   $target  target version, or 0 (zero) fully revert (optional)
     * @return String
     */
    public function getRollback($env = null, $target = null)
    {
        $command = ['rollback', '-e' => $env ?: $this->env];
        if (isset($target)) {
            // Need to use isset() with rollback, because -t0 is a valid option!
            // See http://docs.phinx.org/en/latest/commands.html#the-rollback-command
            $command += ['-t' => $target];
        }
        return $this->executeRun($command);
    }

    protected function executeRun(Array $command)
    {
        // Output will be written to a temporary stream, so that it can be
        // collected after running the command.
        $stream = fopen('php://temp', 'w+');

        // Execute the command, capturing the output in the temporary stream
        // and storing the exit code for debugging purposes.
        $this->exit_code = $this->app->doRun(new ArrayInput($command), new StreamOutput($stream));

        // Get the output of the command and close the stream, which will
        // destroy the temporary file.
        $result = stream_get_contents($stream, -1, 0);
        fclose($stream);

        return $result;
    }
}
