<?php

declare(strict_types=1);

namespace Tests\Invoice;

use Osimatic\Invoice\FacturXGenerator;
use Osimatic\Invoice\InvoiceInterface;
use Osimatic\Invoice\InvoiceProductInterface;
use Osimatic\Organization\OrganizationInterface;
use Osimatic\Text\PDFGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class FacturXGeneratorTest extends TestCase
{
	private string $tempDir;

	protected function setUp(): void
	{
		$this->tempDir = sys_get_temp_dir().'/facturx_generator_test_'.uniqid();
		mkdir($this->tempDir);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->tempDir.'/*') ?: [] as $file) {
			unlink($file);
		}
		rmdir($this->tempDir);
	}

	/**
	 * @return InvoiceInterface
	 */
	private function createInvoice(): InvoiceInterface
	{
		$seller = $this->createMock(OrganizationInterface::class);
		$seller->method('getName')->willReturn('MyTime SAS');
		$seller->method('getRegistrationNumber')->willReturn('12345678900012');

		$buyer = $this->createMock(OrganizationInterface::class);
		$buyer->method('getName')->willReturn('Mairie de Test');
		$buyer->method('getRegistrationNumber')->willReturn('98765432100034');

		$product = $this->createMock(InvoiceProductInterface::class);
		$product->method('getLabel')->willReturn('Abonnement mensuel');
		$product->method('getUnitPrice')->willReturn(100.0);
		$product->method('getQuantity')->willReturn(1.0);
		$product->method('getVatRate')->willReturn(20.0);
		$product->method('getVatCategory')->willReturn(\Osimatic\Invoice\VatCategory::STANDARD);

		$invoice = $this->createMock(InvoiceInterface::class);
		$invoice->method('getSeller')->willReturn($seller);
		$invoice->method('getBuyer')->willReturn($buyer);
		$invoice->method('getInvoiceNumber')->willReturn('INV-2026-001');
		$invoice->method('getDate')->willReturn(new \DateTime('2026-09-01'));
		$invoice->method('getCurrency')->willReturn('EUR');
		$invoice->method('getCustomerOrderReference')->willReturn('PO-42');
		$invoice->method('getProductsList')->willReturn([$product]);
		$invoice->method('getTotalExclTax')->willReturn(100.0);
		$invoice->method('getTotalVat')->willReturn(20.0);
		$invoice->method('getTotalInclTax')->willReturn(120.0);

		return $invoice;
	}

	/**
	 * Creates a minimal valid PDF file for testing, using the real Dompdf-based PDFGenerator (a hand-written PDF fixture is too easy to get subtly wrong, e.g. an incorrect xref table, which FPDI then rejects).
	 * @return string Path to the temporary PDF file
	 */
	private function createMinimalPdfFile(): string
	{
		$path = $this->tempDir.'/source.pdf';

		(new PDFGenerator())->generateFile($path, '<html><body><h1>Test invoice</h1></body></html>');

		return $path;
	}

	/* ===================== generate() ===================== */

	public function testGenerate(): void
	{
		$invoice = $this->createInvoice();
		$pdfPath = $this->createMinimalPdfFile();
		$outputPath = $this->tempDir.'/facturx.pdf';

		$result = (new FacturXGenerator())->generate($invoice, $pdfPath, $outputPath);

		// The returned path is normalized by FileSystem::formatPath() (directory separators are platform-specific)
		$this->assertSame(\Osimatic\FileSystem\FileSystem::formatPath($outputPath), $result);
		$this->assertFileExists($outputPath);

		// Unreadable source PDF -> null, nothing written
		$this->assertNull((new FacturXGenerator())->generate($invoice, $this->tempDir.'/does-not-exist.pdf', $outputPath.'2'));
		$this->assertFileDoesNotExist($outputPath.'2');
	}
}