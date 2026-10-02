<?php

declare(strict_types=1);

namespace Tests\Text;

use Osimatic\Text\PDF;
use Osimatic\Text\PDFGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PDFGeneratorTest extends TestCase
{
	private string $tempDir;

	protected function setUp(): void
	{
		$this->tempDir = sys_get_temp_dir().'/pdfgenerator_test_'.uniqid();
	}

	protected function tearDown(): void
	{
		if (is_dir($this->tempDir)) {
			$this->removeDirectory($this->tempDir);
		}
	}

	/* ===================== Constructor & Configuration ===================== */

	public function testConstructor(): void
	{
		// Default logger
		$generator = new PDFGenerator();
		$this->assertInstanceOf(PDFGenerator::class, $generator);

		// Custom logger
		$logger = new NullLogger();
		$generator = new PDFGenerator($logger);
		$this->assertInstanceOf(PDFGenerator::class, $generator);
	}

	public function testSetLogger(): void
	{
		$generator = new PDFGenerator();
		$result = $generator->setLogger(new NullLogger());

		$this->assertSame($generator, $result);
	}

	/* ===================== generateFile() ===================== */

	public function testGenerateFile(): void
	{
		$filePath = $this->tempDir.'/invoice.pdf';

		// Simple body, no header/footer
		$generator = new PDFGenerator();
		$this->assertTrue($generator->generateFile($filePath, '<html><body><h1>Test invoice</h1></body></html>'));
		$this->assertFileExists($filePath);
		$this->assertSame(1, PDF::getPageCount($filePath));

		// With header and footer, repeated on every page
		$bodyHtml = '<html><body>'.str_repeat('<p>Line of content</p>', 200).'</body></html>';
		$headerHtml = '<html><body><div class="header">My Company</div></body></html>';
		$footerHtml = '<html><body><div class="footer">Page footer</div></body></html>';
		$filePathWithHeaderFooter = $this->tempDir.'/invoice_with_header_footer.pdf';
		$this->assertTrue($generator->generateFile($filePathWithHeaderFooter, $bodyHtml, $headerHtml, $footerHtml));
		$this->assertFileExists($filePathWithHeaderFooter);
		$pageCount = PDF::getPageCount($filePathWithHeaderFooter);
		$this->assertIsInt($pageCount);
		$this->assertGreaterThan(1, $pageCount);
		$decodedText = $this->extractDecompressedText($filePathWithHeaderFooter);
		$this->assertStringContainsString('My Company', $decodedText);
		$this->assertStringContainsString('Page footer', $decodedText);

		// Landscape orientation via the legacy 'orientation' option
		$landscapeFilePath = $this->tempDir.'/invoice_landscape.pdf';
		$this->assertTrue($generator->generateFile($landscapeFilePath, '<html><body>Landscape</body></html>', options: ['orientation' => 'Landscape']));
		$mediaBox = $this->extractFirstMediaBox($landscapeFilePath);
		$this->assertNotNull($mediaBox);
		$this->assertGreaterThan($mediaBox[3], $mediaBox[2]); // width > height in landscape

		// Failure case: destination directory cannot be created (permission denied)
		if (!function_exists('posix_getuid')) {
			// posix extension is unavailable (e.g. Windows), permissions cannot be tested reliably
			$this->markTestSkipped('Cannot test permission failure without the posix extension.');
		}
		if (0 === posix_getuid()) {
			// Running as root bypasses filesystem permissions, this scenario cannot be reproduced
			$this->markTestSkipped('Cannot test permission failure while running as root.');
		}
		$readOnlyDir = $this->tempDir.'/readonly';
		mkdir($readOnlyDir, 0500, true);
		// The underlying mkdir()/file_put_contents() calls emit PHP warnings on this expected failure path; suppressed on purpose here.
		$this->assertFalse(@$generator->generateFile($readOnlyDir.'/sub/invoice.pdf', '<html><body>Test</body></html>'));
		chmod($readOnlyDir, 0700);
	}

	/* ===================== Helper Methods ===================== */

	/**
	 * Decompresses every FlateDecode content stream found in a PDF file and concatenates them, so tests can assert on rendered text even though Dompdf compresses content streams by default.
	 * @param string $filePath
	 * @return string
	 */
	private function extractDecompressedText(string $filePath): string
	{
		$content = file_get_contents($filePath);
		if (false === $content) {
			return '';
		}

		$decoded = '';
		if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $matches)) {
			foreach ($matches[1] as $stream) {
				$uncompressed = @gzuncompress($stream);
				if (false !== $uncompressed) {
					$decoded .= $uncompressed;
				}
			}
		}

		return $decoded;
	}

	/**
	 * @return array{0:float,1:float,2:float,3:float}|null [x0, y0, x1, y1]
	 */
	private function extractFirstMediaBox(string $filePath): ?array
	{
		$content = file_get_contents($filePath);
		if (false === $content || !preg_match('/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/', $content, $matches)) {
			return null;
		}

		return [(float) $matches[1], (float) $matches[2], (float) $matches[3], (float) $matches[4]];
	}

	private function removeDirectory(string $directory): void
	{
		$items = scandir($directory);
		foreach ($items as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}
			$path = $directory.'/'.$item;
			if (is_dir($path)) {
				chmod($path, 0700);
				$this->removeDirectory($path);
			}
			else {
				unlink($path);
			}
		}
		chmod($directory, 0700);
		rmdir($directory);
	}
}
