<?php

declare(strict_types=1);

namespace Tests\Invoice;

use GuzzleHttp\Psr7\Response;
use Osimatic\Invoice\ChorusProClient;
use Osimatic\Invoice\ChorusProInvoiceCategory;
use Osimatic\Invoice\ChorusProRecipientInterface;
use Osimatic\Invoice\ChorusProSubmissionMode;
use Osimatic\Invoice\ChorusProSubmissionStatus;
use Osimatic\Invoice\InvoiceInterface;
use Osimatic\Invoice\InvoiceProductInterface;
use Osimatic\Invoice\InvoiceType;
use Osimatic\Invoice\ChorusProVatType;
use Osimatic\Invoice\VatCategory;
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
		$buyer->method('getChorusProServiceCode')->willReturn(null);
		$buyer->method('getRegistrationNumber')->willReturn('98765432100034');
		$buyer->method('getName')->willReturn('Mairie de Test');

		return $buyer;
	}

	private function createProduct(string $label, float $unitPrice, float $quantity, float $vatRate, VatCategory $vatCategory = VatCategory::STANDARD): InvoiceProductInterface
	{
		$product = $this->createMock(InvoiceProductInterface::class);
		$product->method('getLabel')->willReturn($label);
		$product->method('getUnitPrice')->willReturn($unitPrice);
		$product->method('getQuantity')->willReturn($quantity);
		$product->method('getVatRate')->willReturn($vatRate);
		$product->method('getVatCategory')->willReturn($vatCategory);

		return $product;
	}

	/**
	 * @return InvoiceInterface
	 */
	private function createInvoice(OrganizationInterface $buyer, InvoiceType $type = InvoiceType::INVOICE, ?string $customerOrderReference = 'PO-42', ?string $sellerRegistrationNumber = '12345678900012', string $invoiceNumber = 'INV-2026-001', ?array $products = null)
	{
		$seller = $this->createMock(OrganizationInterface::class);
		$seller->method('getName')->willReturn('MyTime SAS');
		$seller->method('getRegistrationNumber')->willReturn($sellerRegistrationNumber);

		$products ??= [$this->createProduct('Abonnement mensuel', 100.0, 1.0, 20.0)];

		$invoice = $this->createMock(InvoiceInterface::class);
		$invoice->method('getType')->willReturn($type);
		$invoice->method('getBuyer')->willReturn($buyer);
		$invoice->method('getSeller')->willReturn($seller);
		$invoice->method('getInvoiceNumber')->willReturn($invoiceNumber);
		$invoice->method('getDate')->willReturn(new \DateTime('2026-09-01'));
		$invoice->method('getCurrency')->willReturn('EUR');
		$invoice->method('getCustomerOrderReference')->willReturn($customerOrderReference);
		$invoice->method('getProductsList')->willReturn($products);
		$invoice->method('getTotalExclTax')->willReturn(100.0);
		$invoice->method('getTotalVat')->willReturn(20.0);
		$invoice->method('getTotalInclTax')->willReturn(120.0);
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
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertSame(ChorusProSubmissionStatus::NOT_APPLICABLE, $client->submit($invoiceNotEligible)->status);

		// Feature flag disabled: no HTTP call at all, returns false
		$eligibleBuyer = $this->createChorusProRecipientBuyer();
		$invoiceDisabled = $this->createInvoice($eligibleBuyer);
		$clientDisabled = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: false,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertSame(ChorusProSubmissionStatus::DISABLED, $clientDisabled->submit($invoiceDisabled)->status);

		// SAISIE_API happy path: authenticates then submits, marks the invoice as SUBMITTED with the returned id
		$invoiceSaisieApi = $this->createInvoice($this->createChorusProRecipientBuyer());
		$clientSaisieApi = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => '12345'])),
			]),
		);
		$result = $clientSaisieApi->submit($invoiceSaisieApi);
		$this->assertSame(ChorusProSubmissionStatus::SUBMITTED, $result->status);
		$this->assertTrue($result->isSubmitted());
		$this->assertSame('12345', $result->submissionId);
		$this->assertNull($result->error);

		// SAISIE_API: not a real invoice (quotation/pro forma) -> payload cannot be built, no HTTP call, marked as ERROR
		$invoiceWrongType = $this->createInvoice($this->createChorusProRecipientBuyer(), InvoiceType::QUOTATION);
		$clientWrongType = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$result = $clientWrongType->submit($invoiceWrongType);
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $result->status);
		$this->assertFalse($result->isSubmitted());
		$this->assertNull($result->submissionId);
		$this->assertNotEmpty($result->error);

		// SAISIE_API: TYPE_1 category requires a customer order reference; missing here -> ERROR, no HTTP call
		$invoiceMissingRef = $this->createInvoice($this->createChorusProRecipientBuyer(ChorusProInvoiceCategory::TYPE_1), customerOrderReference: null);
		$clientMissingRef = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $clientMissingRef->submit($invoiceMissingRef)->status);

		// Authentication failure (no access_token in the OAuth response): ERROR. Authentication is retried independently for the structure resolution call and the actual submission call, so it is attempted twice (each failing the same way) since a failed attempt is not cached.
		$invoiceAuthFailure = $this->createInvoice($this->createChorusProRecipientBuyer());
		$clientAuthFailure = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				new Response(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
				new Response(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
			]),
		);
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $clientAuthFailure->submit($invoiceAuthFailure)->status);

		// EDI_XML_STRUCT happy path: builds the CII XML itself (real CiiXmlGenerator), submits it as a flux
		$invoiceEdiXml = $this->createInvoice($this->createChorusProRecipientBuyer());
		$clientEdiXml = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::EDI_XML_STRUCT,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => '67890'])),
			]),
		);
		$result = $clientEdiXml->submit($invoiceEdiXml);
		$this->assertSame(ChorusProSubmissionStatus::SUBMITTED, $result->status);
		$this->assertSame('67890', $result->submissionId);

		// DEPOT_PDF_API: missing invoice HTML -> cannot render the PDF, ERROR, no HTTP call
		$invoiceMissingHtml = $this->createInvoice($this->createChorusProRecipientBuyer());
		$clientMissingHtml = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::DEPOT_PDF_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $clientMissingHtml->submit($invoiceMissingHtml, null)->status);

		// DEPOT_PDF_API happy path: renders the PDF (real PDFGenerator), merges it into a Factur-X file (real FacturXGenerator/CiiXmlGenerator), submits it as a file
		$invoiceDepotPdf = $this->createInvoice($this->createChorusProRecipientBuyer());
		$clientDepotPdf = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::DEPOT_PDF_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => 'ABCDE'])),
			]),
		);
		$result = $clientDepotPdf->submit($invoiceDepotPdf, '<html><body><h1>Invoice</h1></body></html>');
		$this->assertSame(ChorusProSubmissionStatus::SUBMITTED, $result->status);
		$this->assertSame('ABCDE', $result->submissionId);

		// API error status (400): the JSON error body must not be mistaken for a successful submission
		$clientApiError = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
				new Response(400, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 20, 'libelle' => 'Invalid field'])),
			]),
		);
		$result = $clientApiError->submit($this->createInvoice($this->createChorusProRecipientBuyer()));
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $result->status);
		$this->assertNull($result->submissionId);
		$this->assertStringContainsString('HTTP 400', $result->error);
		$this->assertStringContainsString('Invalid field', $result->error);

		// Successful HTTP status but no invoice identifier in the response: ERROR, since the submission could not be followed up
		$clientNoId = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0])),
			]),
		);
		$result = $clientNoId->submit($this->createInvoice($this->createChorusProRecipientBuyer()));
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $result->status);
		$this->assertStringContainsString('no invoice identifier', $result->error);

		// Validation, applied whatever the submission mode: each invalid invoice is rejected without any HTTP call
		$invalidInvoices = [
			'seller registration number' => $this->createInvoice($this->createChorusProRecipientBuyer(), sellerRegistrationNumber: null),
			'invoice number' => $this->createInvoice($this->createChorusProRecipientBuyer(), invoiceNumber: ''),
			'product line' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: []),
			'product quantity' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: [$this->createProduct('Abonnement mensuel', 100.0, 0.0, 20.0)]),
			'product label' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: [$this->createProduct('', 100.0, 1.0, 20.0)]),
			'product VAT rate' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: [$this->createProduct('Abonnement mensuel', 100.0, 1.0, -5.0)]),
			'invoicing category' => $this->createInvoice($this->createChorusProRecipientBuyer(null)),
			'TYPE_2 service code' => $this->createInvoice($this->createChorusProRecipientBuyer(ChorusProInvoiceCategory::TYPE_2)),
			'quotation' => $this->createInvoice($this->createChorusProRecipientBuyer(), InvoiceType::QUOTATION),
		];
		foreach (ChorusProSubmissionMode::cases() as $mode) {
			foreach ($invalidInvoices as $case => $invalidInvoice) {
				$clientInvalid = new ChorusProClient(
							submissionMode: $mode,
					clientId: 'id',
					clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
					enabled: true,
					requestExecutor: $this->createRequestExecutor([]),
				);
				$result = $clientInvalid->submit($invalidInvoice, '<html></html>');
				$this->assertSame(ChorusProSubmissionStatus::ERROR, $result->status, $mode->value.' / '.$case);
				$this->assertNotEmpty($result->error, $mode->value.' / '.$case);
			}
		}

		// SAISIE_API payload: one "ligneTva" per VAT rate, VAT rate taken from each product line, and "typeTva" taken from the configured VAT type (not deduced from the rates)
		foreach ([ChorusProVatType::VAT_ON_DEBIT, ChorusProVatType::VAT_FRANCHISE] as $vatType) {
			$httpClient = $this->createMock(ClientInterface::class);
			$httpClient->method('sendRequest')->willReturnCallback(function ($request) use (&$requestBodies) {
				$requestBodies[] = (string) $request->getBody();
				return str_contains((string) $request->getUri(), 'soumettre')
					? new Response(200, ['Content-Type' => 'application/json'], json_encode(['idFacture' => '1']))
					: $this->createOauthTokenResponse();
			});
			$requestBodies = [];
			$clientMultiRate = new ChorusProClient(
					submissionMode: ChorusProSubmissionMode::SAISIE_API,
				clientId: 'id',
				clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
				enabled: true,
				requestExecutor: new HTTPRequestExecutor($httpClient),
				vatType: $vatType,
			);
			$invoiceMultiRate = $this->createInvoice($this->createChorusProRecipientBuyer(), products: [
				$this->createProduct('Standard', 100.0, 1.0, 20.0),
				$this->createProduct('Reduced', 100.0, 2.0, 10.0),
				$this->createProduct('Exempt', 50.0, 1.0, 0.0, VatCategory::EXEMPT),
			]);
			$this->assertTrue($clientMultiRate->submit($invoiceMultiRate)->isSubmitted());

			$payload = json_decode(end($requestBodies), true);
			$this->assertSame($vatType->value,$payload['references']['typeTva']);
			// assertEquals: whole floats are decoded from JSON as integers
			$this->assertEquals([20.0, 10.0, 0.0], array_column($payload['ligneTva'], 'ligneTvaTauxManuel'));
			$this->assertEquals([100.0, 200.0, 50.0], array_column($payload['ligneTva'], 'ligneTvaMontantBaseHtParTaux'));
			$this->assertEquals([20.0, 20.0, 0.0], array_column($payload['ligneTva'], 'ligneTvaMontantTvaParTaux'));
			$this->assertEquals([20.0, 10.0, 0.0], array_column($payload['lignePoste'], 'lignePosteTauxTvaManuel'));
		}
	}

	/* ===================== getInvoiceStatus() ===================== */

	public function testGetInvoiceStatus(): void
	{
		// Happy path
		$client = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['statut' => 'DEPOSEE'])),
			]),
		);
		$this->assertSame(['statut' => 'DEPOSEE'], $client->getInvoiceStatus('12345'));

		// API error status -> null (the error body is not returned as a status)
		$clientApiError = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(500, ['Content-Type' => 'application/json'], json_encode(['libelle' => 'Internal error'])),
			]),
		);
		$this->assertNull($clientApiError->getInvoiceStatus('12345'));

		// Authentication failure -> null, no second HTTP call
		$clientAuthFailure = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				new Response(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
			]),
		);
		$this->assertNull($clientAuthFailure->getInvoiceStatus('12345'));
	}
}
