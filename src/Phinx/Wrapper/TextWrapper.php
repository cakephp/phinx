<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Phinx\Wrapper;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Phinx text wrapper: a way to run `status`, `migrate`, and `rollback` commands
 * and get the output of the command back as plain text.
 *
 * @author Woody Gilk <woody.gilk@gmail.com>
 */
class TextWrapper
{
    /**
     * @var \Phinx\Console\PhinxApplication
     */
    private $app;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var int
     */
    private $exit_code;

    /**
     * @param \Phinx\Console\PhinxApplication $app
     * @param array $options
     */
    public function __construct(PhinxApplication $app, array $options = [])
    {
        $this->app = $app;
        $this->options = $options;
    }

    /**
     * Get the application instance.
     *
     * @return \Phinx\Console\PhinxApplication
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Returns the exit code from the last run command.
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exit_code;
    }

    /**
     * Returns the output from running the "status" command.
     *
     * @param string|null $env environment name (optional)
     *
     * @return string
     */
    public function getStatus($env = null)
    {
        $command = ['status'];
        if ($env ?: $this->hasOption('environment')) {
            $command += ['-e' => $env ?: $this->getOption('environment')];
        }
        if ($this->hasOption('configuration')) {
            $command += ['-c' => $this->getOption('configuration')];
        }
        if ($this->hasOption('parser')) {
            $command += ['-p' => $this->getOption('parser')];
        }
        if ($this->hasOption('format')) {
            $command += ['-f' => $this->getOption('format')];
        }

        return $this->executeRun($command);
    }

    /**
     * Returns the output from running the "migrate" command.
     *
     * @param string|null $env environment name (optional)
     * @param string|null $target target version (optional)
     *
     * @return string
     */
    public function getMigrate($env = null, $target = null)
    {
        $command = ['migrate'];
        if ($env ?: $this->hasOption('environment')) {
            $command += ['-e' => $env ?: $this->getOption('environment')];
        }
        if ($this->hasOption('configuration')) {
            $command += ['-c' => $this->getOption('configuration')];
        }
        if ($this->hasOption('parser')) {
            $command += ['-p' => $this->getOption('parser')];
        }
        if ($target) {
            $command += ['-t' => $target];
        }

        return $this->executeRun($command);
    }

    /**
     * Returns the output from running the "seed:run" command.
     *
     * @param string|null $env environment name
     * @param string|null $target target version
     * @param array|string|null $seed array of seed names or seed name
     *
     * @return string
     */
    public function getSeed($env = null, $target = null, $seed = null)
    {
        $command = ['seed:run'];
        if ($env ?: $this->hasOption('environment')) {
            $command += ['-e' => $env ?: $this->getOption('environment')];
        }
        if ($this->hasOption('configuration')) {
            $command += ['-c' => $this->getOption('configuration')];
        }
        if ($this->hasOption('parser')) {
            $command += ['-p' => $this->getOption('parser')];
        }
        if ($target) {
            $command += ['-t' => $target];
        }
        if ($seed) {
            $seed = (array)$seed;
            $command += ['-s' => $seed];
        }

        return $this->executeRun($command);
    }

    /**
     * Returns the output from running the "rollback" command.
     *
     * @param string|null $env environment name (optional)
     * @param mixed $target target version, or 0 (zero) fully revert (optional)
     *
     * @return string
     */
    public function getRollback($env = null, $target = null)
    {
        $command = ['rollback'];
        if ($env ?: $this->hasOption('environment')) {
            $command += ['-e' => $env ?: $this->getOption('environment')];
        }
        if ($this->hasOption('configuration')) {
            $command += ['-c' => $this->getOption('configuration')];
        }
        if ($this->hasOption('parser')) {
            $command += ['-p' => $this->getOption('parser')];
        }
        if (isset($target)) {
            // Need to use isset() with rollback, because -t0 is a valid option!
            // See https://book.cakephp.org/phinx/0/en/commands.html#the-rollback-command
            $command += ['-t' => $target];
        }

        return $this->executeRun($command);
    }

    /**
     * Check option from options array
     *
     * @param string $key
     *
     * @return bool
     */
    protected function hasOption($key)
    {
        return isset($this->options[$key]);
    }

    /**
     * Get option from options array
     *
     * @param string $key
     *
     * @return string|null
     */
    protected function getOption($key)
    {
        if (!isset($this->options[$key])) {
            return null;
        }

        return $this->options[$key];
    }

    /**
     * Set option in options array
     *
     * @param string $key
     * @param string $value
     *
     * @return object
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Execute a command, capturing output and storing the exit code.
     *
     * @param array $command
     *
     * @return string
     */
    protected function executeRun(array $command)
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
