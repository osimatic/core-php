<?php

namespace Osimatic\System;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Utility class for executing system commands using Symfony Process component.
 * Provides logging, error handling, and configurable execution options.
 */
class SystemCommandRunner
{
	/**
	 * The PSR-3 logger instance.
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Default timeout for command execution in seconds (null = no timeout).
	 * @var float|null
	 */
	private ?float $timeout = 60.0;

	/**
	 * Working directory for command execution (null = current directory).
	 * @var string|null
	 */
	private ?string $workingDirectory = null;

	/**
	 * Environment variables for command execution (null = inherit from parent).
	 * @var array|null
	 */
	private ?array $environmentVariables = null;

	/**
	 * Create a new SystemCommandRunner instance.
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 */
	public function __construct(LoggerInterface $logger = new NullLogger())
	{
		$this->logger = $logger;
	}

	/**
	 * Sets the logger for error and debugging information.
	 * @param LoggerInterface $logger The PSR-3 logger instance
	 * @return self Returns this instance for method chaining
	 */
	public function setLogger(LoggerInterface $logger): self
	{
		$this->logger = $logger;

		return $this;
	}

	/**
	 * Set the default timeout for command execution.
	 * @param float|null $timeout Timeout in seconds (null = no timeout)
	 * @return self Returns this instance for method chaining
	 */
	public function setTimeout(?float $timeout): self
	{
		$this->timeout = $timeout;

		return $this;
	}

	/**
	 * Set the working directory for command execution.
	 * @param string|null $workingDirectory Working directory path (null = current directory)
	 * @return self Returns this instance for method chaining
	 */
	public function setWorkingDirectory(?string $workingDirectory): self
	{
		$this->workingDirectory = $workingDirectory;

		return $this;
	}

	/**
	 * Set environment variables for command execution.
	 * @param array|null $environmentVariables Environment variables (null = inherit from parent)
	 * @return self Returns this instance for method chaining
	 */
	public function setEnvironmentVariables(?array $environmentVariables): self
	{
		$this->environmentVariables = $environmentVariables;

		return $this;
	}

	/**
	 * Execute a system command and return success status.
	 * Logs the command execution and any errors that occur.
	 * @param string|array $command Command to execute (string or array of arguments)
	 * @param float|null $timeout Optional timeout override (null = use default timeout)
	 * @return bool True if command executed successfully, false otherwise
	 */
	public function run(string|array $command, ?float $timeout = null): bool
	{
		$process = $this->createProcess($command, $timeout);

		try {
			$process->run();

			$this->logger->info('Executed command: ' . $process->getCommandLine());

			if (!$process->isSuccessful()) {
				$this->logger->error('Command failed: ' . $process->getErrorOutput(), [
					'command' => $process->getCommandLine(),
					'exit_code' => $process->getExitCode(),
					'output' => $process->getOutput(),
				]);
				return false;
			}

			return true;
		} catch (\Exception $e) {
			$this->logger->error('Command execution exception: ' . $e->getMessage(), [
				'command' => $process->getCommandLine(),
				'exception' => $e,
			]);
			return false;
		}
	}

	/**
	 * Execute a system command and return the output.
	 * Throws an exception if the command fails.
	 * @param string|array $command Command to execute (string or array of arguments)
	 * @param float|null $timeout Optional timeout override (null = use default timeout)
	 * @return string Command output (stdout)
	 * @throws ProcessFailedException If the command fails
	 */
	public function execute(string|array $command, ?float $timeout = null): string
	{
		$process = $this->createProcess($command, $timeout);

		try {
			$process->mustRun();

			$this->logger->info('Executed command: ' . $process->getCommandLine());

			return $process->getOutput();
		} catch (ProcessFailedException $e) {
			$this->logger->error('Command failed: ' . $e->getMessage(), [
				'command' => $process->getCommandLine(),
				'exit_code' => $process->getExitCode(),
				'output' => $process->getOutput(),
				'error_output' => $process->getErrorOutput(),
			]);
			throw $e;
		}
	}

	/**
	 * Execute a system command and return detailed results.
	 * Does not throw exceptions, returns a result object with all information.
	 * @param string|array $command Command to execute (string or array of arguments)
	 * @param float|null $timeout Optional timeout override (null = use default timeout)
	 * @return SystemCommandResult Result object containing output, errors, and exit code
	 */
	public function runWithResult(string|array $command, ?float $timeout = null): SystemCommandResult
	{
		$process = $this->createProcess($command, $timeout);

		try {
			$process->run();

			$this->logger->info('Executed command: ' . $process->getCommandLine());

			if (!$process->isSuccessful()) {
				$this->logger->error('Command failed: ' . $process->getErrorOutput(), [
					'command' => $process->getCommandLine(),
					'exit_code' => $process->getExitCode(),
				]);
			}

			return new SystemCommandResult(
				$process->isSuccessful(),
				$process->getOutput(),
				$process->getErrorOutput(),
				$process->getExitCode()
			);
		} catch (\Exception $e) {
			$this->logger->error('Command execution exception: ' . $e->getMessage(), [
				'command' => $process->getCommandLine(),
				'exception' => $e,
			]);

			return new SystemCommandResult(
				false,
				'',
				$e->getMessage(),
				-1
			);
		}
	}

	/**
	 * Create a Process instance with configured options.
	 * @param string|array $command Command to execute
	 * @param float|null $timeout Timeout override
	 * @return Process
	 */
	private function createProcess(string|array $command, ?float $timeout = null): Process
	{
		$process = is_array($command)
			? new Process(array_filter($command), $this->workingDirectory, $this->environmentVariables)
			: Process::fromShellCommandline($command, $this->workingDirectory, $this->environmentVariables);

		$process->setTimeout($timeout ?? $this->timeout);

		return $process;
	}
}