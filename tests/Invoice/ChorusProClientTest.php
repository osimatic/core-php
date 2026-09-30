<?php

declare(strict_types=1);

namespace Tests\Invoice;

use GuzzleHttp\Psr7\Response;
use Osimatic\Invoice\ChorusProClient;
use Osimatic\Invoice\ChorusProEnvironment;
use Osimatic\Invoice\ChorusProInvoiceCategory;
use Osimatic\Invoice\ChorusProRecipientInterface;
use Osimatic\Invoice\ChorusProSubmissionMode;
use Osimatic\Invoice\ChorusProSubmissionStatus;
use Osimatic\Invoice\ChorusProSubmissionTrackingInterface;
use Osimatic\Invoice\InvoiceInterface;
use Osimatic\Invoice\InvoiceProductInterface;
use Osimatic\Invoice\InvoiceType;
use Osimatic\Bank\PaymentMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Osimatic\Organization\OrganizationInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

#[AllowMockObjectsWithoutExpectations]
final class ChorusProClientTest extends TestCase
{
	/**
	 * @param Response[] $responses
	 * @return HTTPRequestExecutor
	 */
	private function createRequestExecutor(array $responses): HTTPRequestExecutor
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects($this->exactly(count($responses)))
			->method('sendRequest')
			->willReturnOnConsecutiveCalls(...$responses);

		return new HTTPRequestExecutor($httpClient);
	}

	/**
	 * @return Response
	 */
	private function createOauthTokenResponse(): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], json_encode([
			'access_token' => 'test-access-token',
			'expires_in' => 3600,
		]));
	}

	/**
	 * @return OrganizationInterface&ChorusProRecipientInterface
	 */
	private function createChorusProRecipientBuyer(?ChorusProInvoiceCategory $category = ChorusProInvoiceCategory::TYPE_3, bool $isRecipient = true)
	{
		$buyer = $this->createMockForIntersectionOfInterfaces([OrganizationInterface::class, ChorusProRecipientInterface::class]);
		$buyer->method('isChorusProRecipient')->willReturn($isRecipient);
		$buyer->method('getChorusProInvoiceCategory')->willReturn($category);
		$buyer->method('getChorusProServiceSiret')->willReturn(null);
		$buyer->method('getChorusProServiceCode')->willReturn(null);
		$buyer->method('getRegistrationNumber')->willReturn('98765432100034');
		$buyer->method('getName')->willReturn('Mairie de Test');

		return $buyer;
	}

	/**
	 * @return InvoiceInterface&ChorusProSubmissionTrackingInterface
	 */
	private function createInvoice(OrganizationInterface $buyer, InvoiceType $type = InvoiceType::INVOICE, ?string $customerOrderReference = 'PO-42')
	{
		$seller = $this->createMock(OrganizationInterface::class);
		$seller->method('getName')->willReturn('MyTime SAS');
		$seller->method('getRegistrationNumber')->willReturn('12345678900012');

		$product = $this->createMock(InvoiceProductInterface::class);
		$product->method('getLabel')->willReturn('Abonnement mensuel');
		$product->method('getUnitPrice')->willReturn(100.0);
		$product->method('getQuantity')->willReturn(1.0);

		$invoice = $this->createMockForIntersectionOfInterfaces([InvoiceInterface::class, ChorusProSubmissionTrackingInterface::class]);
		$invoice->method('getType')->willReturn($type);
		$invoice->method('getBuyer')->willReturn($buyer);
		$invoice->method('getSeller')->willReturn($seller);
		$invoice->method('getInvoiceNumber')->willReturn('INV-2026-001');
		$invoice->method('getDate')->willReturn(new \DateTime('2026-09-01'));
		$invoice->method('getCurrency')->willReturn('EUR');
		$invoice->method('getCustomerOrderReference')->willReturn($customerOrderReference);
		$invoice->method('getProductsList')->willReturn([$product]);
		$invoice->method('getTotalExclTax')->willReturn(100.0);
		$invoice->method('getTotalVat')->willReturn(20.0);
		$invoice->method('getTotalInclTax')->willReturn(120.0);
		$invoice->method('getBillingTaxRate')->willReturn(20.0);
		$invoice->method('getPaymentMethod')->willReturn(PaymentMethod::TRANSFER);

		return $invoice;
	}

	/* ===================== submit() ===================== */

	public function testSubmit(): void
	{
		// Not a Chorus Pro recipient: no HTTP call at all, returns false
		$plainBuyer = $this->createMock(OrganizationInterface::class);
		$invoiceNotEligible = $this->createInvoice($plainBuyer);
		$client = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertFalse($client->submit($invoiceNotEligible));

		// Feature flag disabled: no HTTP call at all, returns false
		$eligibleBuyer = $this->createChorusProRecipientBuyer();
		$invoiceDisabled = $this->createInvoice($eligibleBuyer);
		$clientDisabled = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: false,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertFalse($clientDisabled->submit($invoiceDisabled));

		// SAISIE_API happy path: authenticates then submits, marks the invoice as SUBMITTED with the returned id
		$invoiceSaisieApi = $this->createInvoice($this->createChorusProRecipientBuyer());
		$invoiceSaisieApi->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::SUBMITTED);
		$invoiceSaisieApi->expects($this->once())->method('setChorusProSubmissionId')->with('12345');
		$invoiceSaisieApi->expects($this->once())->method('setChorusProSubmissionDateTime');
		$invoiceSaisieApi->expects($this->once())->method('setChorusProSubmissionError')->with(null);
		$clientSaisieApi = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => '12345'])),
			]),
		);
		$this->assertTrue($clientSaisieApi->submit($invoiceSaisieApi));

		// SAISIE_API: not a real invoice (quotation/pro forma) -> payload cannot be built, no HTTP call, marked as ERROR
		$invoiceWrongType = $this->createInvoice($this->createChorusProRecipientBuyer(), InvoiceType::QUOTATION);
		$invoiceWrongType->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::ERROR);
		$clientWrongType = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertFalse($clientWrongType->submit($invoiceWrongType));

		// SAISIE_API: TYPE_1 category requires a customer order reference; missing here -> ERROR, no HTTP call
		$invoiceMissingRef = $this->createInvoice($this->createChorusProRecipientBuyer(ChorusProInvoiceCategory::TYPE_1), customerOrderReference: null);
		$invoiceMissingRef->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::ERROR);
		$clientMissingRef = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertFalse($clientMissingRef->submit($invoiceMissingRef));

		// Authentication failure (no access_token in the OAuth response): ERROR, no second HTTP call
		$invoiceAuthFailure = $this->createInvoice($this->createChorusProRecipientBuyer());
		$invoiceAuthFailure->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::ERROR);
		$clientAuthFailure = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				new Response(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
			]),
		);
		$this->assertFalse($clientAuthFailure->submit($invoiceAuthFailure));

		// EDI_XML_STRUCT happy path: builds the CII XML itself (real CiiXmlGenerator), submits it as a flux
		$invoiceEdiXml = $this->createInvoice($this->createChorusProRecipientBuyer());
		$invoiceEdiXml->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::SUBMITTED);
		$clientEdiXml = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::EDI_XML_STRUCT,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => '67890'])),
			]),
		);
		$this->assertTrue($clientEdiXml->submit($invoiceEdiXml));

		// DEPOT_PDF_API: missing invoice HTML -> cannot render the PDF, ERROR, no HTTP call
		$invoiceMissingHtml = $this->createInvoice($this->createChorusProRecipientBuyer());
		$invoiceMissingHtml->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::ERROR);
		$clientMissingHtml = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::DEPOT_PDF_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertFalse($clientMissingHtml->submit($invoiceMissingHtml, null));

		// DEPOT_PDF_API happy path: renders the PDF (real PDFGenerator), merges it into a Factur-X file (real FacturXGenerator/CiiXmlGenerator), submits it as a file
		$invoiceDepotPdf = $this->createInvoice($this->createChorusProRecipientBuyer());
		$invoiceDepotPdf->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::SUBMITTED);
		$clientDepotPdf = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::DEPOT_PDF_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => 'ABCDE'])),
			]),
		);
		$this->assertTrue($clientDepotPdf->submit($invoiceDepotPdf, '<html><body><h1>Invoice</h1></body></html>'));
	}

	/* ===================== getInvoiceStatus() ===================== */

	public function testGetInvoiceStatus(): void
	{
		// Happy path
		$client = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['statut' => 'DEPOSEE'])),
			]),
		);
		$this->assertSame(['statut' => 'DEPOSEE'], $client->getInvoiceStatus('12345'));

		// Authentication failure -> null, no second HTTP call
		$clientAuthFailure = new ChorusProClient(
			environment: ChorusProEnvironment::SANDBOX,
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				new Response(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
			]),
		);
		$this->assertNull($clientAuthFailure->getInvoiceStatus('12345'));
	}
}
