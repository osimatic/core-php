<?php

namespace Osimatic\System;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs Symfony console commands (bin/console) in a separate PHP process, either asynchronously or fully detached.
 * @link https://symfony.com/doc/current/components/process.html
 * @link https://symfony.com/doc/current/console.html
 */
readonly class ConsoleCommandRunner implements ConsoleCommandRunnerInterface
{
	/**
	 * @param string $projectDir Project root directory, used as working directory of the process
	 * @param LoggerInterface $logger The PSR-3 logger instance (default: NullLogger)
	 * @param string $consolePath Path of the console script, relative to the project directory
	 * @param string|null $logDir Directory where detached command logs are written (null = "<projectDir>/var/log")
	 * @param float $defaultTimeout Default timeout in seconds for runCommand()
	 */
	public function __construct(
		protected string $projectDir,
		protected LoggerInterface $logger = new NullLogger(),
		protected string $consolePath = 'bin/console',
		protected ?string $logDir = null,
		protected float $defaultTimeout = 30.0,
	) {}

	/**
	 * {@inheritdoc}
	 */
	public function runCommand(string $commandName, array $options = [], array $env = [], ?float $timeOut = null): Process
	{
		$process = new Process($this->buildConsoleCommand($commandName, $options), $this->projectDir);

		$this->logger->info('Console command executed.', ['command' => $process->getCommandLine()]);

		if (!empty($env)) {
			// setEnv replaces the process environment, so it is merged with $_ENV
			$process->setEnv($env + $_ENV);
		}

		$process->setTimeout($timeOut ?? $this->defaultTimeout);
		$process->start();

		return $process;
	}

	/**
	 * {@inheritdoc}
	 */
	public function runCommandDetached(string $commandName, array $options = [], array $env = []): void
	{
		$jobId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));

		$logDir = $this->logDir ?? $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log';
		if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
			throw new \RuntimeException(sprintf('Directory "%s" was not created', $logDir));
		}
		$logPath = $logDir . DIRECTORY_SEPARATOR . 'cmd_' . $jobId . '.log';

		$cmd = $this->buildConsoleCommand($commandName, $options, quiet: false);
		$consoleCmdLine = implode(' ', array_map(escapeshellarg(...), $cmd));

		// The working directory is the project directory so that the console script is found
		$process = Process::fromShellCommandline(self::buildDetachedLauncherCommandLine($consoleCmdLine, $logPath), $this->projectDir);

		if (!empty($env)) {
			$process->setEnv($env + $_ENV);
		}

		$this->logger->info('Launching detached console command.', [
			'job_id' => $jobId,
			'launcher' => $process->getCommandLine(),
			'log_path' => $logPath,
		]);

		// Short timeout for the launcher only, not for the launched job
		$process->setTimeout(10);
		$process->run();

		if (!$process->isSuccessful()) {
			$this->logger->error('Detached launcher failed.', [
				'job_id' => $jobId,
				'stderr' => $process->getErrorOutput(),
			]);

			throw new \RuntimeException('Unable to launch the detached process: ' . $process->getErrorOutput());
		}
	}

	/**
	 * Builds the argument list of a console command.
	 * @param string $commandName Name of the console command
	 * @param array<int|string, mixed> $options Command options
	 * @param bool $quiet True to add the quiet flag (-q)
	 * @return string[] The command as an array of arguments
	 */
	protected function buildConsoleCommand(string $commandName, array $options, bool $quiet = true): array
	{
		$cmd = [
			(new PhpExecutableFinder())->find(false),
			$this->consolePath,
			$commandName,
		];

		foreach (array_filter($options, fn($v) => $v !== null) as $key => $value) {
			self::addOption($cmd, $key, $value);
		}

		$cmd[] = '--no-interaction';
		if ($quiet) {
			$cmd[] = '-q';
		}

		return $cmd;
	}

	/**
	 * Builds the cross-platform launcher command line that detaches the console command.
	 * - Windows: cmd.exe /c start "" /B <cmd> >> log 2>&1
	 * - Linux/macOS: sh -lc 'nohup <cmd> >> log 2>&1 &'
	 * @param string $consoleCmdLine The escaped console command line
	 * @param string $logPath Path of the log file receiving stdout and stderr
	 * @return string
	 */
	private static function buildDetachedLauncherCommandLine(string $consoleCmdLine, string $logPath): string
	{
		if (self::isWindows()) {
			// start "": empty window title (required when a quoted path is used), /B: no new window
			return sprintf('cmd.exe /c start "" /B %s >> %s 2>&1', $consoleCmdLine, escapeshellarg($logPath));
		}

		// nohup: survive the end of the parent process, &: detach
		$wrapped = sprintf('nohup %s >> %s 2>&1 &', $consoleCmdLine, escapeshellarg($logPath));

		return 'sh -lc ' . escapeshellarg($wrapped);
	}

	private static function isWindows(): bool
	{
		return DIRECTORY_SEPARATOR === '\\' || stripos(PHP_OS_FAMILY, 'Windows') !== false;
	}

	/**
	 * Appends an option to the argument list.
	 * - ['--no-debug'] (int key, string value): option already formatted, added as is if it starts with "-"
	 * - ['user_id' => 408]: added as --user_id=408 (booleans become 1/0, arrays repeat the option, blank strings are ignored)
	 * @param array $cmd The argument list, modified by reference
	 * @param string|int $key
	 * @param mixed $value
	 */
	private static function addOption(array &$cmd, string|int $key, mixed $value): void
	{
		if (is_int($key)) {
			if (is_string($value) && str_starts_with($opt = trim($value), '-')) {
				$cmd[] = $opt;
			}
			return;
		}

		foreach (is_array($value) ? $value : [$value] as $v) {
			if (is_bool($v)) {
				$v = $v ? '1' : '0';
			}
			elseif (is_string($v)) {
				$v = trim($v);
			}

			if ($v === null || $v === '') {
				continue;
			}

			$cmd[] = sprintf('--%s=%s', $key, (string) $v);
		}
	}
}