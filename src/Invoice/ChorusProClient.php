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
	) {}

	// ========== Submission ==========

	/**
	 * Submits an invoice to Chorus Pro, using the configured submission mode. Never throws: any failure is logged and reflected via ChorusProSubmissionTrackingInterface (if implemented by the invoice) and the boolean return value, so a Chorus Pro failure never blocks the normal invoicing flow.
	 * @param InvoiceInterface $invoice
	 * @param string|null $invoiceHtml The rendered HTML of the invoice, required only for the DEPOT_PDF_API mode
	 * @return bool True if the invoice was successfully submitted, false if not applicable or on failure
	 */
	public function submit(InvoiceInterface $invoice, ?string $invoiceHtml = null): bool
	{
		$buyer = $invoice->getBuyer();
		if (!$buyer instanceof ChorusProRecipientInterface || !$buyer->isChorusProRecipient()) {
			return false; // not a Chorus Pro recipient, nothing to do
		}

		if (!$this->enabled) {
			return false; // feature flag off (no PISTE account yet)
		}

		try {
			$response = match ($this->submissionMode) {
				ChorusProSubmissionMode::SAISIE_API => $this->submitViaSaisieApi($invoice),
				ChorusProSubmissionMode::EDI_XML_STRUCT => $this->submitViaEdiXmlStruct($invoice),
				ChorusProSubmissionMode::DEPOT_PDF_API => $this->submitViaDepotPdfApi($invoice, $invoiceHtml),
			};
		}
		catch (\Throwable $e) {
			$this->logger->error('Chorus Pro submission failed unexpectedly: '.$e->getMessage());
			$this->markAsError($invoice, $e->getMessage());
			return false;
		}

		if (null === $response) {
			$this->markAsError($invoice, 'Chorus Pro submission returned no response.');
			return false;
		}

		$this->markAsSubmitted($invoice, $response);
		return true;
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
		if (null === ($payload = $this->buildSaisieApiPayload($invoice))) {
			return null;
		}

		return $this->callSoumettreFacture($payload);
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

	// ========== SAISIE_API payload ==========

	/**
	 * Builds the structured JSON payload expected by Chorus Pro in SAISIE_API mode, or null if the invoice is not eligible (wrong document type, or a mandatory reference is missing for the recipient's invoicing category).
	 * @param InvoiceInterface $invoice
	 * @return array|null
	 */
	private function buildSaisieApiPayload(InvoiceInterface $invoice): ?array
	{
		if (InvoiceType::INVOICE !== $invoice->getType()) {
			$this->logger->error('Chorus Pro only accepts real invoices, not quotations or pro forma documents.');
			return null;
		}

		/** @var ChorusProRecipientInterface $buyer */
		$buyer = $invoice->getBuyer();

		if (null === ($category = $buyer->getChorusProInvoiceCategory())) {
			$this->logger->error('Chorus Pro submission requires the recipient\'s invoicing category (ChorusProInvoiceCategory) to be set.');
			return null;
		}
		if (ChorusProInvoiceCategory::TYPE_1 === $category && empty($invoice->getCustomerOrderReference())) {
			$this->logger->error('Chorus Pro invoicing category TYPE_1 requires a customer order reference (engagement number), none was provided.');
			return null;
		}
		if (ChorusProInvoiceCategory::TYPE_2 === $category && empty($buyer->getChorusProServiceCode())) {
			$this->logger->error('Chorus Pro invoicing category TYPE_2 requires a service code, none was provided.');
			return null;
		}

		return [
			'modeDepot' => ChorusProSubmissionMode::SAISIE_API->value,
			'destinataire' => [
				'codeDestinataire' => $buyer->getRegistrationNumber(),
				'codeServiceExecutant' => $buyer->getChorusProServiceSiret() ?? $buyer->getRegistrationNumber(),
			],
			'fournisseur' => [
				'idFournisseur' => $invoice->getSeller()?->getRegistrationNumber(),
			],
			'cadreDeFacturation' => [
				'codeCadreFacturation' => self::DEFAULT_INVOICING_FRAMEWORK_CODE,
			],
			'references' => [
				'deviseFacture' => $invoice->getCurrency(),
				'typeFacture' => 'FACTURE',
				'typeTva' => $invoice->getBillingTaxRate() > 0 ? 'TVA_SUR_DEBIT' : 'FRANCHISE_EN_BASE',
				'modePaiement' => $this->getPaymentModeCode($invoice->getPaymentMethod()),
			],
			'numeroFactureSaisi' => $invoice->getInvoiceNumber(),
			'numeroBonCommande' => $invoice->getCustomerOrderReference(),
			'lignePoste' => array_map(fn (InvoiceProductInterface $product) => [
				'lignePosteDenomination' => $product->getLabel(),
				'lignePosteQuantite' => $product->getQuantity(),
				'lignePosteUnite' => self::DEFAULT_UNIT_CODE,
				'lignePosteMontantUnitaireHT' => $product->getUnitPrice(),
				'lignePosteTauxTvaManuel' => $invoice->getBillingTaxRate(),
			], $invoice->getProductsList()),
			'ligneTva' => [[
				'ligneTvaMontantBaseHT' => $invoice->getTotalExclTax(),
				'ligneTvaTauxTva' => $invoice->getBillingTaxRate(),
				'ligneTvaMontantTva' => $invoice->getTotalVat(),
			]],
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

	// ========== Tracking ==========

	/**
	 * @param InvoiceInterface $invoice
	 * @param array $response
	 */
	private function markAsSubmitted(InvoiceInterface $invoice, array $response): void
	{
		if (!$invoice instanceof ChorusProSubmissionTrackingInterface) {
			return;
		}
		$invoice->setChorusProSubmissionStatus(ChorusProSubmissionStatus::SUBMITTED);
		$invoice->setChorusProSubmissionId($response['idFacture'] ?? $response['id'] ?? null);
		$invoice->setChorusProSubmissionDateTime(new \DateTime());
		$invoice->setChorusProSubmissionError(null);
	}

	/**
	 * @param InvoiceInterface $invoice
	 * @param string $error
	 */
	private function markAsError(InvoiceInterface $invoice, string $error): void
	{
		if (!$invoice instanceof ChorusProSubmissionTrackingInterface) {
			return;
		}
		$invoice->setChorusProSubmissionStatus(ChorusProSubmissionStatus::ERROR);
		$invoice->setChorusProSubmissionError($error);
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