<?php

declare(strict_types=1);

namespace Tests\System;

use Osimatic\System\SystemCommandResult;
use PHPUnit\Framework\TestCase;

final class SystemCommandResultTest extends TestCase
{
	/* ===================== Constructor ===================== */

	public function testConstructorWithSuccessfulResult(): void
	{
		$result = new SystemCommandResult(
			success: true,
			output: 'Success output',
			errorOutput: '',
			exitCode: 0
		);

		$this->assertInstanceOf(SystemCommandResult::class, $result);
		$this->assertTrue($result->success);
		$this->assertSame('Success output', $result->output);
		$this->assertSame('', $result->errorOutput);
		$this->assertSame(0, $result->exitCode);
	}

	public function testConstructorWithFailedResult(): void
	{
		$result = new SystemCommandResult(
			success: false,
			output: '',
			errorOutput: 'Error message',
			exitCode: 1
		);

		$this->assertInstanceOf(SystemCommandResult::class, $result);
		$this->assertFalse($result->success);
		$this->assertSame('', $result->output);
		$this->assertSame('Error message', $result->errorOutput);
		$this->assertSame(1, $result->exitCode);
	}

	/* ===================== isSuccessful() ===================== */

	public function testIsSuccessfulReturnsTrueForSuccessfulCommand(): void
	{
		$result = new SystemCommandResult(true, 'output', '', 0);

		$this->assertTrue($result->isSuccessful());
	}

	public function testIsSuccessfulReturnsFalseForFailedCommand(): void
	{
		$result = new SystemCommandResult(false, '', 'error', 1);

		$this->assertFalse($result->isSuccessful());
	}

	/* ===================== isFailed() ===================== */

	public function testIsFailedReturnsFalseForSuccessfulCommand(): void
	{
		$result = new SystemCommandResult(true, 'output', '', 0);

		$this->assertFalse($result->isFailed());
	}

	public function testIsFailedReturnsTrueForFailedCommand(): void
	{
		$result = new SystemCommandResult(false, '', 'error', 1);

		$this->assertTrue($result->isFailed());
	}

	/* ===================== getOutput() ===================== */

	public function testGetOutputReturnsOutput(): void
	{
		$result = new SystemCommandResult(true, 'Command output', '', 0);

		$this->assertSame('Command output', $result->getOutput());
	}

	public function testGetOutputReturnsEmptyStringWhenNoOutput(): void
	{
		$result = new SystemCommandResult(false, '', 'error', 1);

		$this->assertSame('', $result->getOutput());
	}

	/* ===================== getErrorOutput() ===================== */

	public function testGetErrorOutputReturnsErrorOutput(): void
	{
		$result = new SystemCommandResult(false, '', 'Error message', 1);

		$this->assertSame('Error message', $result->getErrorOutput());
	}

	public function testGetErrorOutputReturnsEmptyStringWhenNoError(): void
	{
		$result = new SystemCommandResult(true, 'output', '', 0);

		$this->assertSame('', $result->getErrorOutput());
	}

	/* ===================== getExitCode() ===================== */

	public function testGetExitCodeReturnsZeroForSuccess(): void
	{
		$result = new SystemCommandResult(true, 'output', '', 0);

		$this->assertSame(0, $result->getExitCode());
	}

	public function testGetExitCodeReturnsNonZeroForFailure(): void
	{
		$result = new SystemCommandResult(false, '', 'error', 1);

		$this->assertSame(1, $result->getExitCode());
	}

	public function testGetExitCodeWithDifferentExitCodes(): void
	{
		$result1 = new SystemCommandResult(false, '', 'error', 127);
		$this->assertSame(127, $result1->getExitCode());

		$result2 = new SystemCommandResult(false, '', 'error', 255);
		$this->assertSame(255, $result2->getExitCode());

		$result3 = new SystemCommandResult(false, '', 'error', -1);
		$this->assertSame(-1, $result3->getExitCode());
	}

	/* ===================== getCombinedOutput() ===================== */

	public function testGetCombinedOutputWithOnlyStdout(): void
	{
		$result = new SystemCommandResult(true, 'stdout content', '', 0);

		$this->assertSame('stdout content', $result->getCombinedOutput());
	}

	public function testGetCombinedOutputWithOnlyStderr(): void
	{
		$result = new SystemCommandResult(false, '', 'stderr content', 1);

		$this->assertSame("\nstderr content", $result->getCombinedOutput());
	}

	public function testGetCombinedOutputWithBothStdoutAndStderr(): void
	{
		$result = new SystemCommandResult(false, 'stdout content', 'stderr content', 1);

		$expected = "stdout content\nstderr content";
		$this->assertSame($expected, $result->getCombinedOutput());
	}

	public function testGetCombinedOutputWithNoOutput(): void
	{
		$result = new SystemCommandResult(true, '', '', 0);

		$this->assertSame('', $result->getCombinedOutput());
	}

	/* ===================== Readonly Properties ===================== */

	public function testReadonlyPropertiesAreAccessible(): void
	{
		$result = new SystemCommandResult(
			success: true,
			output: 'test output',
			errorOutput: 'test error',
			exitCode: 42
		);

		$this->assertTrue($result->success);
		$this->assertSame('test output', $result->output);
		$this->assertSame('test error', $result->errorOutput);
		$this->assertSame(42, $result->exitCode);
	}

	/* ===================== Real-World Scenarios ===================== */

	public function testSuccessfulSystemCommandResult(): void
	{
		$result = new SystemCommandResult(
			success: true,
			output: "PHP 8.2.0 (cli)\nCopyright (c) The PHP Group",
			errorOutput: '',
			exitCode: 0
		);

		$this->assertTrue($result->isSuccessful());
		$this->assertFalse($result->isFailed());
		$this->assertStringContainsString('PHP', $result->getOutput());
		$this->assertEmpty($result->getErrorOutput());
		$this->assertSame(0, $result->getExitCode());
	}

	public function testFailedCommandWithErrorOutput(): void
	{
		$result = new SystemCommandResult(
			success: false,
			output: '',
			errorOutput: 'php: command not found',
			exitCode: 127
		);

		$this->assertFalse($result->isSuccessful());
		$this->assertTrue($result->isFailed());
		$this->assertEmpty($result->getOutput());
		$this->assertStringContainsString('not found', $result->getErrorOutput());
		$this->assertSame(127, $result->getExitCode());
	}

	public function testCommandWithBothOutputs(): void
	{
		$result = new SystemCommandResult(
			success: false,
			output: 'Processing...',
			errorOutput: 'Warning: deprecated function used',
			exitCode: 1
		);

		$this->assertFalse($result->isSuccessful());
		$this->assertStringContainsString('Processing', $result->getOutput());
		$this->assertStringContainsString('Warning', $result->getErrorOutput());

		$combined = $result->getCombinedOutput();
		$this->assertStringContainsString('Processing', $combined);
		$this->assertStringContainsString('Warning', $combined);
	}
}