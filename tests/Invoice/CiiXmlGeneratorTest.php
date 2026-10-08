<?php

declare(strict_types=1);

namespace Tests\Invoice;

use Osimatic\Invoice\CiiXmlGenerator;
use Osimatic\Invoice\InvoiceInterface;
use Osimatic\Invoice\InvoiceProductInterface;
use Osimatic\Invoice\VatCategory;
use Osimatic\Organization\OrganizationInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CiiXmlGeneratorTest extends TestCase
{
	/**
	 * @return InvoiceInterface
	 */
	private function createInvoice(): InvoiceInterface
	{
		$seller = $this->createMock(OrganizationInterface::class);
		$seller->method('getName')->willReturn('Acme SAS');
		$seller->method('getRegistrationNumber')->willReturn('12345678900012');

		$buyer = $this->createMock(OrganizationInterface::class);
		$buyer->method('getName')->willReturn('Mairie de Test');
		$buyer->method('getRegistrationNumber')->willReturn('98765432100034');

		$product = $this->createMock(InvoiceProductInterface::class);
		$product->method('getLabel')->willReturn('Abonnement mensuel');
		$product->method('getUnitPrice')->willReturn(100.0);
		$product->method('getQuantity')->willReturn(1.0);
		$product->method('getVatRate')->willReturn(20.0);
		$product->method('getVatCategory')->willReturn(VatCategory::STANDARD);

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

	/* ===================== Constants ===================== */

	public function testProviderUniqueIdExtendedConstant(): void
	{
		$this->assertSame('zffxextended', CiiXmlGenerator::PROVIDER_UNIQUE_ID_EXTENDED);
	}

	/* ===================== generate() ===================== */

	public function testGenerate(): void
	{
		$xml = (new CiiXmlGenerator())->generate($this->createInvoice());

		$this->assertNotNull($xml);
		$this->assertStringContainsString('INV-2026-001', $xml);
		$this->assertStringContainsString('Acme SAS', $xml);
		$this->assertStringContainsString('Mairie de Test', $xml);
		$this->assertStringContainsString('Abonnement mensuel', $xml);

		// Invalid provider unique id: the underlying library throws, generate() must catch it and return null instead of propagating the exception
		$invalidGenerator = new CiiXmlGenerator('this-provider-does-not-exist');
		$this->assertNull($invalidGenerator->generate($this->createInvoice()));

		// Missing seller: invalid invoice, generate() must return null
		$invoiceWithoutSeller = $this->createMock(InvoiceInterface::class);
		$invoiceWithoutSeller->method('getSeller')->willReturn(null);
		$invoiceWithoutSeller->method('getBuyer')->willReturn($this->createInvoice()->getBuyer());
		$this->assertNull((new CiiXmlGenerator())->generate($invoiceWithoutSeller));

		// Missing buyer: invalid invoice, generate() must return null
		$invoiceWithoutBuyer = $this->createMock(InvoiceInterface::class);
		$invoiceWithoutBuyer->method('getSeller')->willReturn($this->createInvoice()->getSeller());
		$invoiceWithoutBuyer->method('getBuyer')->willReturn(null);
		$this->assertNull((new CiiXmlGenerator())->generate($invoiceWithoutBuyer));

		// Exception thrown by a getter is caught as well
		$throwingInvoice = $this->createMock(InvoiceInterface::class);
		$throwingInvoice->method('getSeller')->willThrowException(new \RuntimeException('boom'));
		$this->assertNull((new CiiXmlGenerator())->generate($throwingInvoice));
	}
}