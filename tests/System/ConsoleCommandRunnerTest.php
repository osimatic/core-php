<?php

declare(strict_types=1);

namespace Tests\System;

use Osimatic\System\ConsoleCommandRunner;
use Osimatic\System\ConsoleCommandRunnerInterface;
use PHPUnit\Framework\TestCase;

final class ConsoleCommandRunnerTest extends TestCase
{
	private string $projectDir;

	protected function setUp(): void
	{
		$this->projectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'console_runner_' . bin2hex(random_bytes(6));
		mkdir($this->projectDir . DIRECTORY_SEPARATOR . 'bin', 0777, true);

		// Fake console script: prints its arguments as JSON, followed by the value of the TEST_ENV_VAR environment variable
		file_put_contents(
			$this->projectDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console',
			'<?php echo json_encode(array_slice($argv, 1)), "|", getenv("TEST_ENV_VAR") ?: "";'
		);
	}

	protected function tearDown(): void
	{
		self::removeDirectory($this->projectDir);
	}

	private static function removeDirectory(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir($path) ? self::removeDirectory($path) : unlink($path);
		}
		rmdir($dir);
	}

	/**
	 * Runs a command and returns the arguments received by the fake console script.
	 */
	private function getReceivedArguments(string $commandName, array $options = []): array
	{
		$process = (new ConsoleCommandRunner($this->projectDir))->runCommand($commandName, $options);
		$process->wait();

		$this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

		return json_decode(explode('|', $process->getOutput())[0], true);
	}

	/* ===================== runCommand ===================== */

	public function testImplementsInterface(): void
	{
		$this->assertInstanceOf(ConsoleCommandRunnerInterface::class, new ConsoleCommandRunner($this->projectDir));
	}

	public function testRunCommandWithoutOptions(): void
	{
		$this->assertSame(['app:test', '--no-interaction', '-q'], $this->getReceivedArguments('app:test'));
	}

	public function testRunCommandReturnsStartedProcessWithWorkingDirectory(): void
	{
		$process = (new ConsoleCommandRunner($this->projectDir))->runCommand('app:test');

		$this->assertSame($this->projectDir, $process->getWorkingDirectory());
		$this->assertTrue($process->isStarted());
		$process->wait();
	}

	public function testRunCommandWithKeyValueOptions(): void
	{
		$arguments = $this->getReceivedArguments('app:test', ['user_id' => 408, 'name' => '  John  ']);

		$this->assertSame(['app:test', '--user_id=408', '--name=John', '--no-interaction', '-q'], $arguments);
	}

	public function testRunCommandConvertsBooleansToOneOrZero(): void
	{
		$arguments = $this->getReceivedArguments('app:test', ['enabled' => true, 'disabled' => false]);

		$this->assertSame(['app:test', '--enabled=1', '--disabled=0', '--no-interaction', '-q'], $arguments);
	}

	public function testRunCommandIgnoresNullAndBlankValues(): void
	{
		$arguments = $this->getReceivedArguments('app:test', ['a' => null, 'b' => '', 'c' => '   ', 'd' => [], 'e' => 0]);

		$this->assertSame(['app:test', '--e=0', '--no-interaction', '-q'], $arguments);
	}

	public function testRunCommandRepeatsOptionForArrayValues(): void
	{
		$arguments = $this->getReceivedArguments('app:test', ['id' => [1, 2, '', true, null]]);

		$this->assertSame(['app:test', '--id=1', '--id=2', '--id=1', '--no-interaction', '-q'], $arguments);
	}

	public function testRunCommandPassesPreformattedOptionsAsIs(): void
	{
		$arguments = $this->getReceivedArguments('app:test', ['--no-debug', ' --env=prod ', '-v', 'invalid', '', 12]);

		$this->assertSame(['app:test', '--no-debug', '--env=prod', '-v', '--no-interaction', '-q'], $arguments);
	}

	public function testRunCommandWithCustomConsolePath(): void
	{
		copy(
			$this->projectDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console',
			$this->projectDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'other'
		);

		$process = (new ConsoleCommandRunner($this->projectDir, consolePath: 'bin/other'))->runCommand('app:test');
		$process->wait();

		$this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
	}

	public function testRunCommandMergesAdditionalEnvironment(): void
	{
		$process = (new ConsoleCommandRunner($this->projectDir))->runCommand('app:test', env: ['TEST_ENV_VAR' => 'hello']);
		$process->wait();

		$this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
		$this->assertStringEndsWith('|hello', $process->getOutput());
	}

	public function testRunCommandUsesDefaultTimeout(): void
	{
		$process = (new ConsoleCommandRunner($this->projectDir, defaultTimeout: 12.0))->runCommand('app:test');
		$process->wait();

		$this->assertSame(12.0, $process->getTimeout());
	}

	public function testRunCommandTimeoutOverride(): void
	{
		$process = (new ConsoleCommandRunner($this->projectDir))->runCommand('app:test', timeOut: 5.0);
		$process->wait();

		$this->assertSame(5.0, $process->getTimeout());
	}

	/* ===================== runCommandDetached ===================== */

	public function testRunCommandDetachedWritesOutputToLogFile(): void
	{
		$logDir = $this->projectDir . DIRECTORY_SEPARATOR . 'custom_logs';

		(new ConsoleCommandRunner($this->projectDir, logDir: $logDir))->runCommandDetached('app:detached', ['user_id' => 7]);

		$this->assertDirectoryExists($logDir);

		// The command runs in the background, so the log file is polled for a few seconds
		$content = '';
		for ($i = 0; $i < 50 && !str_contains($content, '|'); $i++) {
			usleep(100_000);
			foreach (glob($logDir . DIRECTORY_SEPARATOR . 'cmd_*.log') ?: [] as $logFile) {
				$content = (string) file_get_contents($logFile);
			}
		}

		// Detached commands are not run in quiet mode
		$this->assertSame(['app:detached', '--user_id=7', '--no-interaction'], json_decode(explode('|', $content)[0], true));
	}

	public function testRunCommandDetachedCreatesDefaultLogDirectory(): void
	{
		(new ConsoleCommandRunner($this->projectDir))->runCommandDetached('app:detached');

		$this->assertDirectoryExists($this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log');

		// Let the background job finish before the temporary directory is removed
		usleep(500_000);
	}

	public function testRunCommandDetachedThrowsWhenLogDirectoryCannotBeCreated(): void
	{
		// A file exists where the log directory should be created
		$blockingFile = $this->projectDir . DIRECTORY_SEPARATOR . 'blocking';
		file_put_contents($blockingFile, '');

		$this->expectException(\RuntimeException::class);

		@(new ConsoleCommandRunner($this->projectDir, logDir: $blockingFile . DIRECTORY_SEPARATOR . 'logs'))->runCommandDetached('app:detached');
	}
}