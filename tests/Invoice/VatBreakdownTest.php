<?php

declare(strict_types=1);

namespace Tests\Invoice;

use Osimatic\Invoice\InvoiceInterface;
use Osimatic\Invoice\InvoiceProductInterface;
use Osimatic\Invoice\VatBreakdown;
use Osimatic\Invoice\VatCategory;
use PHPUnit\Framework\TestCase;

final class VatBreakdownTest extends TestCase
{
	private function createProduct(float $unitPrice, float $quantity, float $vatRate, VatCategory $vatCategory = VatCategory::STANDARD): InvoiceProductInterface
	{
		$product = $this->createStub(InvoiceProductInterface::class);
		$product->method('getUnitPrice')->willReturn($unitPrice);
		$product->method('getQuantity')->willReturn($quantity);
		$product->method('getVatRate')->willReturn($vatRate);
		$product->method('getVatCategory')->willReturn($vatCategory);

		return $product;
	}

	/**
	 * @param InvoiceProductInterface[] $products
	 */
	private function createInvoice(array $products): InvoiceInterface
	{
		$invoice = $this->createStub(InvoiceInterface::class);
		$invoice->method('getProductsList')->willReturn($products);

		return $invoice;
	}

	/* ===================== Constructor ===================== */

	public function testConstructor(): void
	{
		$line = new VatBreakdown(VatCategory::STANDARD, 20.0, 100.0, 20.0);

		$this->assertSame(VatCategory::STANDARD, $line->category);
		$this->assertSame(20.0, $line->rate);
		$this->assertSame(100.0, $line->baseExclTax);
		$this->assertSame(20.0, $line->vatAmount);
	}

	/* ===================== fromInvoice() ===================== */

	public function testFromInvoice(): void
	{
		// No product: empty breakdown
		$this->assertSame([], VatBreakdown::fromInvoice($this->createInvoice([])));

		// Single rate: lines of the same category and rate are summed
		$lines = VatBreakdown::fromInvoice($this->createInvoice([
			$this->createProduct(100.0, 1.0, 20.0),
			$this->createProduct(10.0, 3.0, 20.0),
		]));
		$this->assertCount(1, $lines);
		$this->assertInstanceOf(VatBreakdown::class, $lines[0]);
		$this->assertSame(VatCategory::STANDARD, $lines[0]->category);
		$this->assertSame(20.0, $lines[0]->rate);
		$this->assertSame(130.0, $lines[0]->baseExclTax);
		$this->assertSame(26.0, $lines[0]->vatAmount);

		// Multiple rates: one entry per rate, sorted by rate descending
		$lines = VatBreakdown::fromInvoice($this->createInvoice([
			$this->createProduct(50.0, 1.0, 0.0, VatCategory::ZERO_RATED),
			$this->createProduct(100.0, 2.0, 10.0),
			$this->createProduct(100.0, 1.0, 20.0),
		]));
		$this->assertCount(3, $lines);
		$this->assertSame([20.0, 10.0, 0.0], array_map(fn (VatBreakdown $l) => $l->rate, $lines));
		$this->assertSame([100.0, 200.0, 50.0], array_map(fn (VatBreakdown $l) => $l->baseExclTax, $lines));
		$this->assertSame([20.0, 20.0, 0.0], array_map(fn (VatBreakdown $l) => $l->vatAmount, $lines));

		// Same 0% rate but different categories: kept as distinct entries
		$lines = VatBreakdown::fromInvoice($this->createInvoice([
			$this->createProduct(100.0, 1.0, 0.0, VatCategory::EXEMPT),
			$this->createProduct(40.0, 1.0, 0.0, VatCategory::REVERSE_CHARGE),
			$this->createProduct(10.0, 1.0, 0.0, VatCategory::EXEMPT),
		]));
		$this->assertCount(2, $lines);
		$byCategory = [];
		foreach ($lines as $line) {
			$byCategory[$line->category->value] = $line->baseExclTax;
		}
		$this->assertSame(['E' => 110.0, 'AE' => 40.0], $byCategory);

		// Rounding to 2 decimals
		$lines = VatBreakdown::fromInvoice($this->createInvoice([
			$this->createProduct(0.333, 1.0, 8.5),
		]));
		$this->assertSame(0.33, $lines[0]->baseExclTax);
		$this->assertSame(0.03, $lines[0]->vatAmount);
	}
}