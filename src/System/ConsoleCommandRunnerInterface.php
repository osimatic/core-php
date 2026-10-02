<?php

namespace Osimatic\System;

use Symfony\Component\Process\Process;

/**
 * Runs Symfony console commands (bin/console) in a separate PHP process.
 * @link https://symfony.com/doc/current/components/process.html
 */
interface ConsoleCommandRunnerInterface
{
	/**
	 * Starts a console command asynchronously and returns the running process.
	 * @param string $commandName Name of the console command (e.g. "app:send-emails")
	 * @param array<int|string, mixed> $options Command options: ['key' => 'value'] becomes --key=value, ['--flag'] is passed as is, null and empty values are ignored
	 * @param array<string, string|int|float|bool> $env Additional environment variables merged with $_ENV
	 * @param float|null $timeOut Process timeout in seconds (null = default timeout of the runner)
	 * @return Process The started process
	 */
	public function runCommand(string $commandName, array $options = [], array $env = [], ?float $timeOut = null): Process;

	/**
	 * Starts a console command in the background, detached from the current process, and returns immediately.
	 * The command output is appended to a log file in the log directory.
	 * @param string $commandName Name of the console command (e.g. "app:send-emails")
	 * @param array<int|string, mixed> $options Command options (same format as runCommand)
	 * @param array<string, string|int|float|bool> $env Additional environment variables merged with $_ENV
	 * @throws \RuntimeException If the log directory cannot be created or the detached launcher fails
	 */
	public function runCommandDetached(string $commandName, array $options = [], array $env = []): void;
}