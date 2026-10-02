<?php

namespace Osimatic\System;

/**
 * Immutable value object representing the result of a command execution.
 * Contains the success status, output, error output, and exit code.
 */
readonly class SystemCommandResult
{
	/**
	 * Create a new SystemCommandResult instance.
	 * @param bool $success Whether the command executed successfully (exit code 0)
	 * @param string $output Command output from stdout
	 * @param string $errorOutput Command error output from stderr
	 * @param int $exitCode Command exit code (0 = success, non-zero = error)
	 */
	public function __construct(
		public bool $success,
		public string $output,
		public string $errorOutput,
		public int $exitCode
	) {}

	/**
	 * Check if the command was successful.
	 * @return bool True if exit code is 0
	 */
	public function isSuccessful(): bool
	{
		return $this->success;
	}

	/**
	 * Check if the command failed.
	 * @return bool True if exit code is non-zero
	 */
	public function isFailed(): bool
	{
		return !$this->success;
	}

	/**
	 * Get the command output (stdout).
	 * @return string
	 */
	public function getOutput(): string
	{
		return $this->output;
	}

	/**
	 * Get the command error output (stderr).
	 * @return string
	 */
	public function getErrorOutput(): string
	{
		return $this->errorOutput;
	}

	/**
	 * Get the command exit code.
	 * @return int
	 */
	public function getExitCode(): int
	{
		return $this->exitCode;
	}

	/**
	 * Get the combined output (stdout + stderr).
	 * @return string
	 */
	public function getCombinedOutput(): string
	{
		$output = $this->output;
		if (!empty($this->errorOutput)) {
			$output .= "\n" . $this->errorOutput;
		}
		return $output;
	}
}