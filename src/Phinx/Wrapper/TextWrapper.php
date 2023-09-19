<?php
declare(strict_types=1);

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
 */
class TextWrapper
{
    /**
     * @var \Phinx\Console\PhinxApplication
     */
    protected PhinxApplication $app;

    /**
     * @var array<string, mixed>
     */
    protected array $options;

    /**
     * @var int
     */
    protected int $exitCode;

    /**
     * @param \Phinx\Console\PhinxApplication $app Application
     * @param array<string, mixed> $options Options
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
    public function getApp(): PhinxApplication
    {
        return $this->app;
    }

    /**
     * Returns the exit code from the last run command.
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Returns the output from running the "status" command.
     *
     * @param string|null $env environment name (optional)
     * @return string
     */
    public function getStatus(?string $env = null): string
    {
        $command = ['status'];
        if ($this->hasEnvValue($env)) {
            $command['-e'] = $env ?: $this->getOption('environment');
        }
        if ($this->hasOption('configuration')) {
            $command['-c'] = $this->getOption('configuration');
        }
        if ($this->hasOption('parser')) {
            $command['-p'] = $this->getOption('parser');
        }
        if ($this->hasOption('format')) {
            $command['-f'] = $this->getOption('format');
        }

        return $this->executeRun($command);
    }

    /**
     * @param string|null $env environment name
     * @return bool
     */
    private function hasEnvValue(?string $env): bool
    {
        return $env || $this->hasOption('environment');
    }

    /**
     * Returns the output from running the "migrate" command.
     *
     * @param string|null $env environment name (optional)
     * @param string|null $target target version (optional)
     * @return string
     */
    public function getMigrate(?string $env = null, ?string $target = null): string
    {
        $command = ['migrate'];
        if ($this->hasEnvValue($env)) {
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
     * @param string|null $env Environment name
     * @param string|null $target Target version
     * @param string[]|string|null $seed Array of seed names or seed name
     * @return string
     */
    public function getSeed(?string $env = null, ?string $target = null, array|string|null $seed = null): string
    {
        $command = ['seed:run'];
        if ($this->hasEnvValue($env)) {
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
     * @param string|null $env Environment name (optional)
     * @param mixed $target Target version, or 0 (zero) fully revert (optional)
     * @return string
     */
    public function getRollback(?string $env = null, mixed $target = null): string
    {
        $command = ['rollback'];
        if ($this->hasEnvValue($env)) {
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
     * @param string $key Key
     * @return bool
     */
    protected function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    /**
     * Get option from options array
     *
     * @param string $key Key
     * @return string|null
     */
    protected function getOption(string $key): ?string
    {
        if (!isset($this->options[$key])) {
            return null;
        }

        return $this->options[$key];
    }

    /**
     * Set option in options array
     *
     * @param string $key Key
     * @param string $value Value
     * @return $this
     */
    public function setOption(string $key, string $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Execute a command, capturing output and storing the exit code.
     *
     * @param array $command Command
     * @return string
     */
    protected function executeRun(array $command): string
    {
        // Output will be written to a temporary stream, so that it can be
        // collected after running the command.
        $stream = fopen('php://temp', 'w+');

        // Execute the command, capturing the output in the temporary stream
        // and storing the exit code for debugging purposes.
        $this->exitCode = $this->app->doRun(new ArrayInput($command), new StreamOutput($stream));

        // Get the output of the command and close the stream, which will
        // destroy the temporary file.
        $result = stream_get_contents($stream, -1, 0);
        fclose($stream);

        return $result;
    }
}
