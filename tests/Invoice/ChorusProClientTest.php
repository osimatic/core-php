<?php

declare(strict_types=1);

namespace Tests\Invoice;

use GuzzleHttp\Psr7\Response;
use Osimatic\Invoice\ChorusProClient;
use Osimatic\Invoice\ChorusProInvoiceInterface;
use Osimatic\Invoice\ChorusProInvoiceReferenceRequirement;
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
use Osimatic\Text\PDFSignerInterface;
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
	private function createChorusProRecipientBuyer(?ChorusProInvoiceReferenceRequirement $referenceRequirement = ChorusProInvoiceReferenceRequirement::NONE, bool $isRecipient = true)
	{
		$buyer = $this->createMockForIntersectionOfInterfaces([OrganizationInterface::class, ChorusProRecipientInterface::class]);
		$buyer->method('isChorusProRecipient')->willReturn($isRecipient);
		$buyer->method('getChorusProInvoiceReferenceRequirement')->willReturn($referenceRequirement);
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
		$seller->method('getName')->willReturn('Acme SAS');
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

	/**
	 * Like createInvoice(), but the mock also implements ChorusProInvoiceInterface, to assert on submit()/depositFlux()'s persist() behavior.
	 * @return InvoiceInterface&ChorusProInvoiceInterface
	 */
	private function createChorusProInvoice(OrganizationInterface $buyer, ?ChorusProSubmissionStatus $chorusProSubmissionStatus = null, ?string $chorusProSubmissionId = null, ?string $chorusProSubmissionError = null, ?\DateTime $chorusProSubmissionDateTime = null, string $invoiceNumber = 'INV-2026-001')
	{
		$seller = $this->createMock(OrganizationInterface::class);
		$seller->method('getName')->willReturn('Acme SAS');
		$seller->method('getRegistrationNumber')->willReturn('12345678900012');

		$invoice = $this->createMockForIntersectionOfInterfaces([InvoiceInterface::class, ChorusProInvoiceInterface::class]);
		$invoice->method('getType')->willReturn(InvoiceType::INVOICE);
		$invoice->method('getBuyer')->willReturn($buyer);
		$invoice->method('getSeller')->willReturn($seller);
		$invoice->method('getInvoiceNumber')->willReturn($invoiceNumber);
		$invoice->method('getDate')->willReturn(new \DateTime('2026-09-01'));
		$invoice->method('getCurrency')->willReturn('EUR');
		$invoice->method('getCustomerOrderReference')->willReturn('PO-42');
		$invoice->method('getProductsList')->willReturn([$this->createProduct('Abonnement', 100.0, 1.0, 20.0)]);
		$invoice->method('getTotalExclTax')->willReturn(100.0);
		$invoice->method('getTotalVat')->willReturn(20.0);
		$invoice->method('getTotalInclTax')->willReturn(120.0);
		$invoice->method('getPaymentMethod')->willReturn(PaymentMethod::TRANSFER);
		$invoice->method('getChorusProSubmissionStatus')->willReturn($chorusProSubmissionStatus);
		$invoice->method('getChorusProSubmissionId')->willReturn($chorusProSubmissionId);
		$invoice->method('getChorusProSubmissionError')->willReturn($chorusProSubmissionError);
		$invoice->method('getChorusProSubmissionDateTime')->willReturn($chorusProSubmissionDateTime);

		return $invoice;
	}

	/**
	 * A no-op PDFSignerInterface (just copies the file), to exercise the DEPOT_PDF_SIGNE_API / depositFlux(signed: true) paths without a real signing service.
	 * @return PDFSignerInterface
	 */
	private function createFakePdfSigner(): PDFSignerInterface
	{
		return new class implements PDFSignerInterface {
			public function sign(string $inputPath, string $outputPath): void
			{
				copy($inputPath, $outputPath);
			}
		};
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
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['identifiantFactureCPP' => '12345'])),
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

		// SAISIE_API: ENGAGEMENT_REQUIRED requires a customer order reference; missing here -> ERROR, no HTTP call
		$invoiceMissingRef = $this->createInvoice($this->createChorusProRecipientBuyer(ChorusProInvoiceReferenceRequirement::ENGAGEMENT_REQUIRED), customerOrderReference: null);
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

		// Authentication failure (no access_token in the OAuth response): ERROR, failing fast on the first authentication attempt (the structure resolution call, which runs before the actual submission call) instead of retrying.
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
			]),
		);
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $clientAuthFailure->submit($invoiceAuthFailure)->status);

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
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0, 'pieceJointeId' => 555])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['identifiantFactureCPP' => 'ABCDE'])),
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

		// Successful HTTP status but no invoice identifier in the response: UNKNOWN (ambiguous, the invoice may have been received), not ERROR
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
		$this->assertSame(ChorusProSubmissionStatus::UNKNOWN, $result->status);
		$this->assertStringContainsString('no "identifiantFactureCPP"', $result->error);

		// Validation, applied whatever the submission mode: each invalid invoice is rejected without any HTTP call
		$invalidInvoices = [
			'seller registration number' => $this->createInvoice($this->createChorusProRecipientBuyer(), sellerRegistrationNumber: null),
			'invoice number' => $this->createInvoice($this->createChorusProRecipientBuyer(), invoiceNumber: ''),
			'product line' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: []),
			'product quantity' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: [$this->createProduct('Abonnement mensuel', 100.0, 0.0, 20.0)]),
			'product label' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: [$this->createProduct('', 100.0, 1.0, 20.0)]),
			'product VAT rate' => $this->createInvoice($this->createChorusProRecipientBuyer(), products: [$this->createProduct('Abonnement mensuel', 100.0, 1.0, -5.0)]),
			'invoicing category' => $this->createInvoice($this->createChorusProRecipientBuyer(null)),
			'SERVICE_CODE_REQUIRED service code' => $this->createInvoice($this->createChorusProRecipientBuyer(ChorusProInvoiceReferenceRequirement::SERVICE_CODE_REQUIRED)),
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
				$uri = (string) $request->getUri();
				return match (true) {
					str_contains($uri, 'soumettre') => new Response(200, ['Content-Type' => 'application/json'], json_encode(['identifiantFactureCPP' => '1'])),
					str_contains($uri, 'rechercher') => new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
					default => $this->createOauthTokenResponse(),
				};
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

	public function testSubmitPersistsResultOntoInvoice(): void
	{
		$buyer = $this->createChorusProRecipientBuyer();
		$invoice = $this->createChorusProInvoice($buyer, invoiceNumber: 'INV-2026-003');
		$invoice->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::SUBMITTED);
		$invoice->expects($this->once())->method('setChorusProSubmissionId')->with('99999');
		$invoice->expects($this->once())->method('setChorusProSubmissionDateTime');
		$invoice->expects($this->once())->method('setChorusProSubmissionError')->with(null);

		$client = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['identifiantFactureCPP' => '99999'])),
			]),
		);
		$this->assertTrue($client->submit($invoice)->isSubmitted());
	}

	/* ===================== idempotency guard ===================== */

	public function testIdempotencyGuard(): void
	{
		$previousDateTime = new \DateTime('2026-08-15 10:00:00');

		foreach ([ChorusProSubmissionStatus::SUBMITTED, ChorusProSubmissionStatus::FLUX_SUBMITTED, ChorusProSubmissionStatus::ACCEPTED, ChorusProSubmissionStatus::UNKNOWN] as $status) {
			// submit(): the existing outcome is returned unchanged; no HTTP call at all (enforced by createRequestExecutor([])), but still re-persisted with the same values by the unconditional persist() wrapper
			$buyer = $this->createChorusProRecipientBuyer();
			$invoiceForSubmit = $this->createChorusProInvoice($buyer, $status, 'ALREADY-SUBMITTED', null, $previousDateTime);
			$invoiceForSubmit->expects($this->once())->method('setChorusProSubmissionStatus')->with($status);
			$invoiceForSubmit->expects($this->once())->method('setChorusProSubmissionId')->with('ALREADY-SUBMITTED');

			$client = new ChorusProClient(
				submissionMode: ChorusProSubmissionMode::SAISIE_API,
				clientId: 'id',
				clientSecret: 'secret',
				accountLogin: 'account-login',
				accountPassword: 'account-password',
				enabled: true,
				requestExecutor: $this->createRequestExecutor([]),
			);
			$result = $client->submit($invoiceForSubmit);
			$this->assertSame($status, $result->status, $status->value);
			$this->assertSame('ALREADY-SUBMITTED', $result->submissionId, $status->value);
			$this->assertSame($previousDateTime, $result->dateTime, $status->value);

			// depositFlux(): same guard
			$invoiceForFlux = $this->createChorusProInvoice($buyer, $status, 'ALREADY-SUBMITTED', null, $previousDateTime);

			$fluxClient = new ChorusProClient(
				submissionMode: ChorusProSubmissionMode::SAISIE_API,
				clientId: 'id',
				clientSecret: 'secret',
				accountLogin: 'account-login',
				accountPassword: 'account-password',
				enabled: true,
				requestExecutor: $this->createRequestExecutor([]),
			);
			$this->assertSame($status, $fluxClient->depositFlux($invoiceForFlux, '<html></html>')->status, $status->value);
		}
	}

	/* ===================== DEPOT_PDF_SIGNE_API / depositFlux(signed: true) ===================== */

	public function testSubmitSignedPdfApi(): void
	{
		$client = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::DEPOT_PDF_SIGNE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			pdfSigner: $this->createFakePdfSigner(),
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0, 'pieceJointeId' => 555])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => [['idStructureCPP' => 999]]])),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['identifiantFactureCPP' => 'SIGNED-1'])),
			]),
		);
		$result = $client->submit($this->createInvoice($this->createChorusProRecipientBuyer()), '<html><body><h1>Invoice</h1></body></html>');
		$this->assertSame(ChorusProSubmissionStatus::SUBMITTED, $result->status);
		$this->assertSame('SIGNED-1', $result->submissionId);
	}

	public function testDepositFluxSigned(): void
	{
		$client = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			pdfSigner: $this->createFakePdfSigner(),
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0, 'numeroFluxDepot' => 'FLUX-SIGNED'])),
			]),
		);
		$result = $client->depositFlux($this->createInvoice($this->createChorusProRecipientBuyer()), '<html><body><h1>Invoice</h1></body></html>', signed: true);
		$this->assertSame(ChorusProSubmissionStatus::FLUX_SUBMITTED, $result->status);
		$this->assertSame('FLUX-SIGNED', $result->submissionId);
	}

	/* ===================== uploadFile() ===================== */

	public function testUploadFile(): void
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
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0, 'pieceJointeId' => 777])),
			]),
		);
		$this->assertSame(777, $client->uploadFile('invoice.pdf', 'fake-pdf-content'));

		// codeRetour != 0 -> rejected, RuntimeException carrying Chorus Pro's error message
		$clientRejected = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 10, 'libelle' => 'Invalid file'])),
			]),
		);
		try {
			$clientRejected->uploadFile('invoice.pdf', 'fake-pdf-content');
			$this->fail('Expected a RuntimeException.');
		}
		catch (\RuntimeException $e) {
			$this->assertStringContainsString('Invalid file', $e->getMessage());
		}

		// No "pieceJointeId" in the response -> RuntimeException
		$clientMissingId = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0])),
			]),
		);
		try {
			$clientMissingId->uploadFile('invoice.pdf', 'fake-pdf-content');
			$this->fail('Expected a RuntimeException.');
		}
		catch (\RuntimeException $e) {
			$this->assertStringContainsString('Unable to upload', $e->getMessage());
		}
	}

	/* ===================== resolveStructureId() (via submit()) ===================== */

	public function testResolveStructureId(): void
	{
		// $supplierStructureId configured: skips "rechercher" entirely, the configured id is used directly in the payload
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->method('sendRequest')->willReturnCallback(function ($request) use (&$requestBodies) {
			$requestBodies[] = (string) $request->getBody();
			$uri = (string) $request->getUri();
			return match (true) {
				str_contains($uri, 'soumettre') => new Response(200, ['Content-Type' => 'application/json'], json_encode(['identifiantFactureCPP' => '1'])),
				default => $this->createOauthTokenResponse(),
			};
		});
		$requestBodies = [];
		$client = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: new HTTPRequestExecutor($httpClient),
			supplierStructureId: 777,
		);
		$this->assertTrue($client->submit($this->createInvoice($this->createChorusProRecipientBuyer()))->isSubmitted());
		$payload = json_decode(end($requestBodies), true);
		$this->assertSame(777, $payload['fournisseur']['idFournisseur']);

		// Structure not found (no "idStructureCPP" in the "rechercher" response) -> ERROR, same as any other unexpected failure
		$clientNotFound = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['listeStructures' => []])),
			]),
		);
		$result = $clientNotFound->submit($this->createInvoice($this->createChorusProRecipientBuyer()));
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $result->status);
		$this->assertStringContainsString('Unable to resolve the Chorus Pro structure id', $result->error);
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

	/* ===================== getFluxStatus() ===================== */

	public function testGetFluxStatus(): void
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
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['etatCourantDepotFlux' => 'INTEGRE'])),
			]),
		);
		$this->assertSame(['etatCourantDepotFlux' => 'INTEGRE'], $client->getFluxStatus('FLUX-123'));

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
		$this->assertNull($clientApiError->getFluxStatus('FLUX-123'));

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
		$this->assertNull($clientAuthFailure->getFluxStatus('FLUX-123'));
	}

	/* ===================== depositFlux() ===================== */

	public function testDepositFlux(): void
	{
		// Happy path: renders the PDF (real PDFGenerator), embeds Factur-X (real FacturXGenerator/CiiXmlGenerator), deposits it as a flux (no "soumettreFacture" call, no structure resolution). $signed defaults to false regardless of $submissionMode or whether a signer happens to be configured.
		$client = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0, 'numeroFluxDepot' => 'FLUX-123'])),
			]),
		);
		$result = $client->depositFlux($this->createInvoice($this->createChorusProRecipientBuyer()), '<html><body><h1>Invoice</h1></body></html>');
		$this->assertSame(ChorusProSubmissionStatus::FLUX_SUBMITTED, $result->status);
		$this->assertTrue($result->isSubmitted());
		$this->assertSame('FLUX-123', $result->submissionId);
		$this->assertNull($result->error);

		// Feature flag disabled: no HTTP call at all
		$clientDisabled = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: false,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$this->assertSame(ChorusProSubmissionStatus::DISABLED, $clientDisabled->depositFlux($this->createInvoice($this->createChorusProRecipientBuyer()), '<html></html>')->status);

		// $signed=true but no PDFSignerInterface configured -> ERROR, no HTTP call (generateFacturXFileContent() throws before reaching the network)
		$clientNoSigner = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([]),
		);
		$resultNoSigner = $clientNoSigner->depositFlux($this->createInvoice($this->createChorusProRecipientBuyer()), '<html><body><h1>Invoice</h1></body></html>', signed: true);
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $resultNoSigner->status);
		$this->assertStringContainsString('no PDF signer is configured', $resultNoSigner->error);

		// Missing invoice HTML -> ERROR, no HTTP call
		$this->assertSame(ChorusProSubmissionStatus::ERROR, $clientNoSigner->depositFlux($this->createInvoice($this->createChorusProRecipientBuyer()), null)->status);

		// Not a Chorus Pro recipient -> NOT_APPLICABLE, no HTTP call at all
		$invoiceNotApplicable = $this->createInvoice($this->createChorusProRecipientBuyer(isRecipient: false));
		$this->assertSame(ChorusProSubmissionStatus::NOT_APPLICABLE, $clientNoSigner->depositFlux($invoiceNotApplicable, '<html></html>')->status);

		// No "numeroFluxDepot" in the response -> UNKNOWN, not ERROR (ambiguous: the flux may have been received)
		$clientNoFluxNumber = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0])),
			]),
		);
		$resultNoFluxNumber = $clientNoFluxNumber->depositFlux($this->createInvoice($this->createChorusProRecipientBuyer()), '<html><body><h1>Invoice</h1></body></html>');
		$this->assertSame(ChorusProSubmissionStatus::UNKNOWN, $resultNoFluxNumber->status);
		$this->assertStringContainsString('no "numeroFluxDepot"', $resultNoFluxNumber->error);

		// Like submit(), a successful flux deposit is persisted onto $invoice (FLUX_SUBMITTED, with the flux number as submissionId)
		$clientForPersistCheck = new ChorusProClient(
			submissionMode: ChorusProSubmissionMode::SAISIE_API,
			clientId: 'id',
			clientSecret: 'secret',
			accountLogin: 'account-login',
			accountPassword: 'account-password',
			enabled: true,
			requestExecutor: $this->createRequestExecutor([
				$this->createOauthTokenResponse(),
				new Response(200, ['Content-Type' => 'application/json'], json_encode(['codeRetour' => 0, 'numeroFluxDepot' => 'FLUX-456'])),
			]),
		);
		$buyer = $this->createChorusProRecipientBuyer();
		$invoice = $this->createChorusProInvoice($buyer, invoiceNumber: 'INV-2026-002');
		$invoice->expects($this->once())->method('setChorusProSubmissionStatus')->with(ChorusProSubmissionStatus::FLUX_SUBMITTED);
		$invoice->expects($this->once())->method('setChorusProSubmissionId')->with('FLUX-456');
		$invoice->expects($this->once())->method('setChorusProSubmissionDateTime');
		$invoice->expects($this->once())->method('setChorusProSubmissionError')->with(null);
		$resultForPersistCheck = $clientForPersistCheck->depositFlux($invoice, '<html><body><h1>Invoice</h1></body></html>');
		$this->assertSame(ChorusProSubmissionStatus::FLUX_SUBMITTED, $resultForPersistCheck->status);
	}
}
