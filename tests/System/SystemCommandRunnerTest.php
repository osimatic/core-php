<?php

declare(strict_types=1);

namespace Tests\System;

use Osimatic\System\SystemCommandRunner;
use Osimatic\System\SystemCommandResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ProcessFailedException;

#[AllowMockObjectsWithoutExpectations]
final class SystemCommandRunnerTest extends TestCase
{
	/* ===================== Constructor & Configuration ===================== */

	public function testConstructorWithoutLogger(): void
	{
		$command = new SystemCommandRunner();
		$this->assertInstanceOf(SystemCommandRunner::class, $command);
	}

	public function testConstructorWithLogger(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$command = new SystemCommandRunner($logger);
		$this->assertInstanceOf(SystemCommandRunner::class, $command);
	}

	public function testSetLogger(): void
	{
		$command = new SystemCommandRunner();
		$logger = $this->createMock(LoggerInterface::class);

		$result = $command->setLogger($logger);

		$this->assertSame($command, $result, 'setLogger should return $this for method chaining');
	}

	public function testSetTimeout(): void
	{
		$command = new SystemCommandRunner();
		$result = $command->setTimeout(120.0);

		$this->assertSame($command, $result, 'setTimeout should return $this for method chaining');
	}

	public function testSetTimeoutNull(): void
	{
		$command = new SystemCommandRunner();
		$result = $command->setTimeout(null);

		$this->assertSame($command, $result);
	}

	public function testSetWorkingDirectory(): void
	{
		$command = new SystemCommandRunner();
		$result = $command->setWorkingDirectory(__DIR__);

		$this->assertSame($command, $result, 'setWorkingDirectory should return $this for method chaining');
	}

	public function testSetEnvironmentVariables(): void
	{
		$command = new SystemCommandRunner();
		$env = ['FOO' => 'bar', 'TEST' => 'value'];
		$result = $command->setEnvironmentVariables($env);

		$this->assertSame($command, $result, 'setEnvironmentVariables should return $this for method chaining');
	}

	public function testMethodChaining(): void
	{
		$command = new SystemCommandRunner();
		$logger = $this->createMock(LoggerInterface::class);

		$result = $command
			->setLogger($logger)
			->setTimeout(120.0)
			->setWorkingDirectory(__DIR__)
			->setEnvironmentVariables(['TEST' => 'value']);

		$this->assertSame($command, $result);
	}

	/* ===================== run() Method ===================== */

	public function testRunWithSuccessfulCommand(): void
	{
		$command = new SystemCommandRunner();

		// Use a simple command that should work on all platforms
		$result = $command->run('php --version');

		$this->assertTrue($result);
	}

	public function testRunWithSuccessfulCommandAsArray(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->run(['php', '--version']);

		$this->assertTrue($result);
	}

	public function testRunWithFailedCommand(): void
	{
		$command = new SystemCommandRunner();

		// Use a command that should fail
		$result = $command->run('php --invalid-option-that-does-not-exist');

		$this->assertFalse($result);
	}

	public function testRunLogsSuccessfulExecution(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with($this->stringContains('Executed command:'));

		$command = new SystemCommandRunner($logger);
		$command->run('php --version');
	}

	public function testRunLogsFailedExecution(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				$this->stringContains('Command failed:'),
				$this->arrayHasKey('exit_code')
			);

		$command = new SystemCommandRunner($logger);
		$command->run('php --invalid-option-that-does-not-exist');
	}

	public function testRunWithCustomTimeout(): void
	{
		$command = new SystemCommandRunner();

		// Short timeout for a command that completes quickly
		$result = $command->run('php --version', 5.0);

		$this->assertTrue($result);
	}

	/* ===================== execute() Method ===================== */

	public function testExecuteReturnsOutput(): void
	{
		$command = new SystemCommandRunner();

		$output = $command->execute('php --version');

		$this->assertIsString($output);
		$this->assertStringContainsString('PHP', $output);
	}

	public function testExecuteWithCommandArray(): void
	{
		$command = new SystemCommandRunner();

		$output = $command->execute(['php', '--version']);

		$this->assertIsString($output);
		$this->assertStringContainsString('PHP', $output);
	}

	public function testExecuteThrowsExceptionOnFailure(): void
	{
		$command = new SystemCommandRunner();

		$this->expectException(ProcessFailedException::class);
		$command->execute('php --invalid-option-that-does-not-exist');
	}

	public function testExecuteLogsSuccessfulExecution(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with($this->stringContains('Executed command:'));

		$command = new SystemCommandRunner($logger);
		$command->execute('php --version');
	}

	public function testExecuteLogsFailedExecution(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				$this->stringContains('Command failed:'),
				$this->arrayHasKey('exit_code')
			);

		$command = new SystemCommandRunner($logger);

		try {
			$command->execute('php --invalid-option-that-does-not-exist');
		} catch (ProcessFailedException $e) {
			// Expected exception
		}
	}

	/* ===================== runWithResult() Method ===================== */

	public function testRunWithResultReturnsSystemCommandResult(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->runWithResult('php --version');

		$this->assertInstanceOf(SystemCommandResult::class, $result);
	}

	public function testRunWithResultSuccessfulCommand(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->runWithResult('php --version');

		$this->assertTrue($result->isSuccessful());
		$this->assertFalse($result->isFailed());
		$this->assertSame(0, $result->getExitCode());
		$this->assertStringContainsString('PHP', $result->getOutput());
	}

	public function testRunWithResultFailedCommand(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->runWithResult('php --invalid-option-that-does-not-exist');

		$this->assertFalse($result->isSuccessful());
		$this->assertTrue($result->isFailed());
		$this->assertNotSame(0, $result->getExitCode());
		$this->assertNotEmpty($result->getErrorOutput());
	}

	public function testRunWithResultWithCommandArray(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->runWithResult(['php', '--version']);

		$this->assertTrue($result->isSuccessful());
		$this->assertStringContainsString('PHP', $result->getOutput());
	}

	public function testRunWithResultLogsSuccessfulExecution(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('info')
			->with($this->stringContains('Executed command:'));

		$command = new SystemCommandRunner($logger);
		$command->runWithResult('php --version');
	}

	public function testRunWithResultLogsFailedExecution(): void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->atLeastOnce())
			->method('error');

		$command = new SystemCommandRunner($logger);
		$command->runWithResult('php --invalid-option-that-does-not-exist');
	}

	/* ===================== Working Directory ===================== */

	public function testRunWithWorkingDirectory(): void
	{
		$command = new SystemCommandRunner();
		$command->setWorkingDirectory(__DIR__);

		// This command should work regardless of working directory
		$result = $command->run('php --version');

		$this->assertTrue($result);
	}

	/* ===================== Environment Variables ===================== */

	public function testRunWithEnvironmentVariables(): void
	{
		$command = new SystemCommandRunner();
		$command->setEnvironmentVariables(['TEST_VAR' => 'test_value']);

		// On Unix-like systems
		if (DIRECTORY_SEPARATOR === '/') {
			$result = $command->runWithResult('echo $TEST_VAR');
			$this->assertTrue($result->isSuccessful());
			$this->assertStringContainsString('test_value', $result->getOutput());
		}
		// On Windows
		else {
			$result = $command->runWithResult('echo %TEST_VAR%');
			$this->assertTrue($result->isSuccessful());
			$this->assertStringContainsString('test_value', $result->getOutput());
		}
	}

	/* ===================== Edge Cases ===================== */

	public function testRunWithEmptyCommand(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->run('');

		$this->assertFalse($result);
	}

	public function testRunWithResultEmptyCommand(): void
	{
		$command = new SystemCommandRunner();

		$result = $command->runWithResult('');

		$this->assertFalse($result->isSuccessful());
	}

	public function testExecuteWithEmptyCommand(): void
	{
		$command = new SystemCommandRunner();

		$this->expectException(ProcessFailedException::class);
		$command->execute('');
	}
}