<?php

namespace Osimatic\Invoice;

use Osimatic\Bank\PaymentMethod;
use Osimatic\Network\HTTPMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Osimatic\Text\PDFGenerator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client for the Chorus Pro API (French public administration e-invoicing platform): authenticates against PISTE and submits invoices via the "soumettreFacture" endpoint, in any of its 3 submission modes.
 * Endpoint paths and the SAISIE_API payload fields are based on the Chorus Pro API documentation and third-party integration reports; verify them against the actual PISTE API swagger before going to production.
 * @link https://piste.gouv.fr PISTE developer portal
 * @link https://communaute.chorus-pro.gouv.fr/submit-invoice/?lang=en Chorus Pro "Submit invoice" documentation
 */
class ChorusProClient
{
	// ========== Constants ==========

	// Standard supplier invoice framework, as opposed to e.g. a subcontractor invoice
	private const string DEFAULT_INVOICING_FRAMEWORK_CODE = 'A1_FACTURE_FOURNISSEUR';

	// UN/CEFACT Recommendation 20 generic "unit" code, used as a default since InvoiceProductInterface does not expose a unit of measure
	private const string DEFAULT_UNIT_CODE = 'C62';

	// ========== Properties ==========

	private ?string $accessToken = null;
	private int $accessTokenExpiresAt = 0;

	// ========== Constructor ==========

	public function __construct(
		private readonly ChorusProEnvironment $environment,
		private readonly ChorusProSubmissionMode $submissionMode,
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly bool $enabled = false,
		private readonly string $scope = 'openid',
		private readonly LoggerInterface $logger = new NullLogger(),
		private readonly HTTPRequestExecutor $requestExecutor = new HTTPRequestExecutor(),
		private readonly CiiXmlGenerator $ciiXmlGenerator = new CiiXmlGenerator(),
		private readonly FacturXGenerator $facturXGenerator = new FacturXGenerator(),
		private readonly PDFGenerator $pdfGenerator = new PDFGenerator(),
		private readonly ChorusProVatType $vatType = ChorusProVatType::VAT_ON_DEBIT,
	) {}

	// ========== Submission ==========

	/**
	 * Submits an invoice to Chorus Pro, using the configured submission mode. Never throws: any failure is logged and reported in the returned result, so a Chorus Pro failure never blocks the normal invoicing flow. Persisting the result is up to the caller.
	 * @param InvoiceInterface $invoice
	 * @param string|null $invoiceHtml The rendered HTML of the invoice, required only for the DEPOT_PDF_API mode
	 * @return ChorusProSubmissionResult The outcome: NOT_APPLICABLE (not a Chorus Pro recipient), DISABLED (feature flag off), SUBMITTED (with the Chorus Pro identifier) or ERROR (with the error message)
	 */
	public function submit(InvoiceInterface $invoice, ?string $invoiceHtml = null): ChorusProSubmissionResult
	{
		$buyer = $invoice->getBuyer();
		if (!$buyer instanceof ChorusProRecipientInterface || !$buyer->isChorusProRecipient()) {
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::NOT_APPLICABLE);
		}

		if (!$this->enabled) {
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::DISABLED); // feature flag off (no PISTE account yet)
		}

		if (null !== ($validationError = $this->getInvoiceValidationError($invoice))) {
			$this->logger->error('Chorus Pro submission aborted: '.$validationError);
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::ERROR, error: $validationError);
		}

		try {
			$response = match ($this->submissionMode) {
				ChorusProSubmissionMode::SAISIE_API => $this->submitViaSaisieApi($invoice),
				ChorusProSubmissionMode::EDI_XML_STRUCT => $this->submitViaEdiXmlStruct($invoice),
				ChorusProSubmissionMode::DEPOT_PDF_API => $this->submitViaDepotPdfApi($invoice, $invoiceHtml),
			};
		}
		catch (\Throwable $e) {
			$this->logger->error('Chorus Pro submission failed unexpectedly: '.$e->getMessage(), ['exception' => $e]);
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::ERROR, error: $e->getMessage());
		}

		if (null === $response) {
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::ERROR, error: 'Chorus Pro submission returned no response.');
		}

		return new ChorusProSubmissionResult(ChorusProSubmissionStatus::SUBMITTED, submissionId: $response['idFacture'] ?? $response['id'] ?? null);
	}

	/**
	 * Gets the status of a previously submitted invoice.
	 * @param string $submissionId
	 * @return array|null
	 */
	public function getInvoiceStatus(string $submissionId): ?array
	{
		if (null === ($accessToken = $this->getAccessToken())) {
			$this->logger->error('Chorus Pro status lookup aborted: could not authenticate against PISTE.');
			return null;
		}

		return $this->requestExecutor->execute(HTTPMethod::GET, $this->environment->getApiBaseUri().'consulterFacture', [
			'idFacture' => $submissionId,
		], ['Authorization' => 'Bearer '.$accessToken], decodeJson: true);
	}

	// ========== Submission modes ==========

	/**
	 * @param InvoiceInterface $invoice
	 * @return array|null
	 */
	private function submitViaSaisieApi(InvoiceInterface $invoice): ?array
	{
		return $this->callSoumettreFacture($this->buildSaisieApiPayload($invoice));
	}

	/**
	 * @param InvoiceInterface $invoice
	 * @return array|null
	 */
	private function submitViaEdiXmlStruct(InvoiceInterface $invoice): ?array
	{
		if (null === ($xml = $this->ciiXmlGenerator->generate($invoice))) {
			return null;
		}

		return $this->callSoumettreFacture([
			'modeDepot' => ChorusProSubmissionMode::EDI_XML_STRUCT->value,
			'flux' => base64_encode($xml),
		]);
	}

	/**
	 * @param InvoiceInterface $invoice
	 * @param string|null $invoiceHtml
	 * @return array|null
	 */
	private function submitViaDepotPdfApi(InvoiceInterface $invoice, ?string $invoiceHtml): ?array
	{
		if (null === $invoiceHtml) {
			$this->logger->error('Chorus Pro DEPOT_PDF_API mode requires the invoice HTML to render the PDF.');
			return null;
		}

		$tmpDir = sys_get_temp_dir();
		$tmpPdfPath = $tmpDir.'/chorus_pro_'.uniqid('', true).'.pdf';
		$tmpFacturXPath = $tmpDir.'/chorus_pro_facturx_'.uniqid('', true).'.pdf';

		try {
			if (!$this->pdfGenerator->generateFile($tmpPdfPath, $invoiceHtml)) {
				return null;
			}
			if (null === $this->facturXGenerator->generate($invoice, $tmpPdfPath, $tmpFacturXPath)) {
				return null;
			}
			if (!is_readable($tmpFacturXPath) || false === ($fileContent = file_get_contents($tmpFacturXPath))) {
				$this->logger->error('Unable to read the generated Factur-X file: '.$tmpFacturXPath);
				return null;
			}

			return $this->callSoumettreFacture([
				'modeDepot' => ChorusProSubmissionMode::DEPOT_PDF_API->value,
				'fichier' => [
					'nomFichier' => ($invoice->getInvoiceNumber() ?: 'invoice').'.pdf',
					'contenuFichier' => base64_encode($fileContent),
				],
			]);
		}
		finally {
			@unlink($tmpPdfPath);
			@unlink($tmpFacturXPath);
		}
	}

	// ========== Validation ==========

	/**
	 * Checks that the invoice holds everything Chorus Pro requires, whatever the submission mode: document type, supplier and recipient identification, invoice number, currency, at least one product line, and the references mandatory for the recipient's invoicing category.
	 * The invoice is expected to have already been checked as being addressed to a Chorus Pro recipient.
	 * @param InvoiceInterface $invoice
	 * @return string|null The description of the first problem found, or null if the invoice is valid
	 */
	private function getInvoiceValidationError(InvoiceInterface $invoice): ?string
	{
		if (InvoiceType::INVOICE !== $invoice->getType()) {
			return 'Chorus Pro only accepts real invoices, not quotations or pro forma documents.';
		}

		if (empty($invoice->getSeller()?->getRegistrationNumber())) {
			return 'the supplier (seller) registration number (SIRET) is missing.';
		}
		if (empty($invoice->getInvoiceNumber())) {
			return 'the invoice number is missing.';
		}
		if (empty($invoice->getCurrency())) {
			return 'the invoice currency is missing.';
		}
		if (empty($invoice->getProductsList())) {
			return 'the invoice has no product line.';
		}

		/** @var ChorusProRecipientInterface&\Osimatic\Organization\OrganizationInterface $buyer */
		$buyer = $invoice->getBuyer();

		if (empty($buyer->getRegistrationNumber())) {
			return 'the recipient registration number (SIRET) is missing.';
		}
		if (null === ($category = $buyer->getChorusProInvoiceCategory())) {
			return 'the recipient\'s invoicing category (ChorusProInvoiceCategory) is missing.';
		}
		if (ChorusProInvoiceCategory::TYPE_1 === $category && empty($invoice->getCustomerOrderReference())) {
			return 'invoicing category TYPE_1 requires a customer order reference (engagement number), none was provided.';
		}
		if (ChorusProInvoiceCategory::TYPE_2 === $category && empty($buyer->getChorusProServiceCode())) {
			return 'invoicing category TYPE_2 requires a service code, none was provided.';
		}

		return null;
	}

	// ========== SAISIE_API payload ==========

	/**
	 * Builds the structured JSON payload expected by Chorus Pro in SAISIE_API mode. The invoice must have been validated beforehand (see getInvoiceValidationError()).
	 * @param InvoiceInterface $invoice
	 * @return array
	 */
	private function buildSaisieApiPayload(InvoiceInterface $invoice): array
	{
		/** @var ChorusProRecipientInterface $buyer */
		$buyer = $invoice->getBuyer();

		$vatBreakdown = VatBreakdown::fromInvoice($invoice);

		return [
			'modeDepot' => ChorusProSubmissionMode::SAISIE_API->value,
			'destinataire' => [
				'codeDestinataire' => $buyer->getRegistrationNumber(),
				'codeServiceExecutant' => $buyer->getChorusProServiceSiret() ?? $buyer->getRegistrationNumber(),
			],
			'fournisseur' => [
				'idFournisseur' => $invoice->getSeller()->getRegistrationNumber(),
			],
			'cadreDeFacturation' => [
				'codeCadreFacturation' => self::DEFAULT_INVOICING_FRAMEWORK_CODE,
			],
			'references' => [
				'deviseFacture' => $invoice->getCurrency(),
				'typeFacture' => 'FACTURE',
				'typeTva' => $this->vatType->value,
				'modePaiement' => $this->getPaymentModeCode($invoice->getPaymentMethod()),
			],
			'numeroFactureSaisi' => $invoice->getInvoiceNumber(),
			'numeroBonCommande' => $invoice->getCustomerOrderReference(),
			'lignePoste' => array_map(fn (InvoiceProductInterface $product) => [
				'lignePosteDenomination' => $product->getLabel(),
				'lignePosteQuantite' => $product->getQuantity(),
				'lignePosteUnite' => self::DEFAULT_UNIT_CODE,
				'lignePosteMontantUnitaireHT' => $product->getUnitPrice(),
				'lignePosteTauxTvaManuel' => $product->getVatRate(),
			], $invoice->getProductsList()),
			'ligneTva' => array_map(fn (VatBreakdown $line) => [
				'ligneTvaMontantBaseHT' => $line->baseExclTax,
				'ligneTvaTauxTva' => $line->rate,
				'ligneTvaMontantTva' => $line->vatAmount,
			], $vatBreakdown),
			'montantTotal' => [
				'montantHtTotal' => $invoice->getTotalExclTax(),
				'montantTVA' => $invoice->getTotalVat(),
				'montantTtcTotal' => $invoice->getTotalInclTax(),
			],
		];
	}

	/**
	 * Maps our generic payment method to the Chorus Pro payment mode code.
	 * @param PaymentMethod|null $paymentMethod
	 * @return string
	 */
	private function getPaymentModeCode(?PaymentMethod $paymentMethod): string
	{
		return match ($paymentMethod) {
			PaymentMethod::TRANSFER => 'VIREMENT',
			PaymentMethod::DIRECT_DEBIT => 'PRELEVEMENT',
			PaymentMethod::CHEQUE => 'CHEQUE',
			default => 'AUTRE',
		};
	}

	// ========== HTTP / OAuth2 ==========

	/**
	 * @param array $payload
	 * @return array|null
	 */
	private function callSoumettreFacture(array $payload): ?array
	{
		if (null === ($accessToken = $this->getAccessToken())) {
			$this->logger->error('Chorus Pro submission aborted: could not authenticate against PISTE.');
			return null;
		}

		$response = $this->requestExecutor->execute(HTTPMethod::POST, $this->environment->getApiBaseUri().'soumettreFacture', $payload, [
			'Authorization' => 'Bearer '.$accessToken,
		], jsonBody: true, decodeJson: true);

		if (!is_array($response)) {
			$this->logger->error('Chorus Pro submission failed: no valid response from the API.');
			return null;
		}

		return $response;
	}

	/**
	 * Gets a valid PISTE access token, requesting a new one if none is cached or the cached one has expired.
	 * The token is cached for the lifetime of this instance, which is sufficient since a single request/console run only needs to authenticate once.
	 * @return string|null
	 */
	private function getAccessToken(): ?string
	{
		if (null !== $this->accessToken && time() < $this->accessTokenExpiresAt) {
			return $this->accessToken;
		}

		$response = $this->requestExecutor->execute(HTTPMethod::POST, $this->environment->getOauthUri(), [
			'grant_type' => 'client_credentials',
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'scope' => $this->scope,
		], decodeJson: true);

		if (!is_array($response) || empty($response['access_token'])) {
			$this->logger->error('Failed to authenticate against PISTE: no access token in response.');
			return null;
		}

		$this->accessToken = $response['access_token'];
		$this->accessTokenExpiresAt = time() + max(0, ((int) ($response['expires_in'] ?? 0)) - 30); // 30s safety margin

		return $this->accessToken;
	}
}