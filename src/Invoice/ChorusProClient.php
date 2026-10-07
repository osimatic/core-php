<?php

namespace Osimatic\Invoice;

use Osimatic\Bank\PaymentMethod;
use Osimatic\Network\HTTPMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Osimatic\Organization\OrganizationInterface;
use Osimatic\Text\PDFGenerator;
use Osimatic\Text\PDFSignerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client for the Chorus Pro API (French public administration e-invoicing platform): authenticates against PISTE and submits invoices via the "soumettreFacture" endpoint, in any of its 3 submission modes.
 * Two distinct sets of credentials are required: the PISTE OAuth2 client ID/secret (identifies the application) and a Chorus Pro account login/password (identifies the Chorus Pro user), sent on every call as the base64-encoded "cpro-account" header. The Chorus Pro account is created separately on the Chorus Pro portal, not on PISTE.
 * Endpoint paths and SAISIE_API payload field names are confirmed against the official PISTE API catalog Swagger schema ("API de Test pour Factures")
 * @link https://piste.gouv.fr PISTE developer portal
 * @link https://chorus-pro.gouv.fr/qualif Chorus Pro qualification (sandbox) account creation
 * @link https://communaute.chorus-pro.gouv.fr/submit-invoice/?lang=en Chorus Pro "Submit invoice" documentation
 */
class ChorusProClient
{
	// ========== Constants ==========

	// PISTE OAuth2 token endpoints. Historical *.aife.economie.gouv.fr URLs were decommissioned on 2023-09-30, replaced by piste.gouv.fr.
	// @link https://piste.gouv.fr/decommissionnement-des-url-piste-historiques Historical PISTE URLs decommissioning notice
	public const string SANDBOX_OAUTH_URI = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
	public const string PRODUCTION_OAUTH_URI = 'https://oauth.piste.gouv.fr/api/oauth/token';

	// Chorus Pro API domain, shared by all PISTE "cpro" products (Factures, Structures, etc.). The full route of each product, e.g. "cpro/factures/v1/soumettre", is passed to callApi().
	public const string SANDBOX_API_BASE_URI = 'https://sandbox-api.piste.gouv.fr';
	public const string PRODUCTION_API_BASE_URI = 'https://api.piste.gouv.fr';

	// Standard supplier invoice framework, as opposed to e.g. a subcontractor invoice
	private const string DEFAULT_INVOICING_FRAMEWORK_CODE = 'A1_FACTURE_FOURNISSEUR';

	// UN/CEFACT Recommendation 20 generic "unit" code, used as a default since InvoiceProductInterface does not expose a unit of measure
	private const string DEFAULT_UNIT_CODE = 'C62';

	// ========== Properties ==========

	private ?string $accessToken = null;
	private int $accessTokenExpiresAt = 0;

	// Caches the Chorus Pro internal structure id ("idStructureCPP") resolved for a given SIRET by resolveStructureId(), keyed by SIRET
	private array $structureIdCache = [];

	// ========== Constructor ==========

	public function __construct(
		private ChorusProSubmissionMode $submissionMode,
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly string $accountLogin,
		private readonly string $accountPassword,
		private readonly bool $enabled = false,
		private readonly string $scope = 'openid',
		private readonly LoggerInterface $logger = new NullLogger(),
		private readonly HTTPRequestExecutor $requestExecutor = new HTTPRequestExecutor(),
		private readonly FacturXGenerator $facturXGenerator = new FacturXGenerator(),
		private readonly PDFGenerator $pdfGenerator = new PDFGenerator(),
		private readonly ?PDFSignerInterface $pdfSigner = null,
		private readonly ChorusProVatType $vatType = ChorusProVatType::VAT_ON_DEBIT,
		private readonly ?int $supplierStructureId = null, // the Chorus Pro internal structure id ("idStructureCPP") of the supplier (our own SIRET); if null, resolveStructureId() resolves it via the API instead
		private readonly bool $sandbox = true, // true to target the PISTE sandbox (default), false for production
	) {}

	/**
	 * Overrides the submission mode configured at construction, e.g. to reuse an injected instance under a different mode without declaring a second service.
	 * @param ChorusProSubmissionMode $submissionMode
	 */
	public function setSubmissionMode(ChorusProSubmissionMode $submissionMode): void
	{
		$this->submissionMode = $submissionMode;
	}

	// ========== Submission ==========

	/**
	 * Submits an invoice to Chorus Pro, using the configured submission mode. Never throws: any failure is logged and reported in the returned result, so a Chorus Pro failure never blocks the normal invoicing flow.
	 * If $invoice implements ChorusProInvoiceInterface, an invoice already SUBMITTED, ACCEPTED or UNKNOWN is not resubmitted (its previous outcome is returned unchanged), and the outcome of this call is persisted onto the invoice automatically; otherwise, persisting the returned result is up to the caller.
	 * @param InvoiceInterface $invoice
	 * @param string|null $invoiceHtml The rendered HTML of the invoice, required only for the DEPOT_PDF_API/DEPOT_PDF_SIGNE_API modes
	 * @return ChorusProSubmissionResult The outcome: NOT_APPLICABLE (not a Chorus Pro recipient), DISABLED (feature flag off), SUBMITTED (with the Chorus Pro identifier), UNKNOWN (ambiguous outcome, do not resubmit) or ERROR (with the error message)
	 */
	public function submit(InvoiceInterface $invoice, ?string $invoiceHtml = null): ChorusProSubmissionResult
	{
		$result = $this->doSubmit($invoice, $invoiceHtml);

		if ($invoice instanceof ChorusProInvoiceInterface) {
			$invoice->setChorusProSubmissionStatus($result->status);
			$invoice->setChorusProSubmissionId($result->submissionId);
			$invoice->setChorusProSubmissionDateTime($result->dateTime);
			$invoice->setChorusProSubmissionError($result->error);
		}

		return $result;
	}

	/**
	 * @param InvoiceInterface $invoice
	 * @param string|null $invoiceHtml
	 * @return ChorusProSubmissionResult
	 */
	private function doSubmit(InvoiceInterface $invoice, ?string $invoiceHtml): ChorusProSubmissionResult
	{
		$buyer = $invoice->getBuyer();
		if (!$buyer instanceof ChorusProRecipientInterface || !$buyer->isChorusProRecipient()) {
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::NOT_APPLICABLE);
		}

		// SUBMITTED/ACCEPTED: already successfully submitted, nothing to do. UNKNOWN: Chorus Pro's answer could not be determined, so the invoice may already have been received — must not be resubmitted blindly either, pending manual verification.
		if ($invoice instanceof ChorusProInvoiceInterface && \in_array($invoice->getChorusProSubmissionStatus(), [ChorusProSubmissionStatus::SUBMITTED, ChorusProSubmissionStatus::ACCEPTED, ChorusProSubmissionStatus::UNKNOWN], true)) {
			return new ChorusProSubmissionResult(
				$invoice->getChorusProSubmissionStatus(),
				submissionId: $invoice->getChorusProSubmissionId(),
				error: $invoice->getChorusProSubmissionError(),
				dateTime: $invoice->getChorusProSubmissionDateTime() ?? new \DateTime(),
			);
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
				ChorusProSubmissionMode::DEPOT_PDF_API => $this->submitViaPdfApi($invoice, $invoiceHtml, false),
				ChorusProSubmissionMode::DEPOT_PDF_SIGNE_API => $this->submitViaPdfApi($invoice, $invoiceHtml, true),
			};
		}
		catch (\Throwable $e) {
			$this->logger->error('Chorus Pro submission failed unexpectedly: '.$e->getMessage(), ['exception' => $e]);
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::ERROR, error: $e->getMessage());
		}

		// No response at all (e.g. connection dropped mid-request): Chorus Pro may have received and processed the submission before the connection failed, so this is genuinely UNKNOWN rather than a definitive ERROR, to avoid blindly resubmitting (duplicate).
		if (null === $response) {
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::UNKNOWN, error: 'Chorus Pro submission returned no response.');
		}

		// A 2xx HTTP response does not necessarily mean success: Chorus Pro reports business errors via "codeRetour" inside an otherwise-2xx response. This is a definitive rejection, not ambiguous, hence ERROR.
		if (0 !== ($response['codeRetour'] ?? 0)) {
			$this->logger->error('Chorus Pro submission rejected: '.mb_substr(json_encode($response), 0, 500));
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::ERROR, error: 'Chorus Pro rejected the submission (codeRetour '.$response['codeRetour'].'): '.($response['libelle'] ?? ''));
		}

		// A 2xx response was received (the invoice was processed) but without an identifier, the submission cannot be followed up: genuinely UNKNOWN rather than a definitive ERROR, to avoid blindly resubmitting (duplicate).
		if (null === ($submissionId = $response['identifiantFactureCPP'] ?? null)) {
			$this->logger->error('Chorus Pro response contains no invoice identifier: '.mb_substr(json_encode($response), 0, 500));
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::UNKNOWN, error: 'Chorus Pro response contains no invoice identifier. The invoice may have been received: check in Chorus Pro before submitting it again.');
		}

		return new ChorusProSubmissionResult(ChorusProSubmissionStatus::SUBMITTED, submissionId: (string) $submissionId);
	}

	/**
	 * Gets the status of a previously submitted invoice, via the "consulterHistoriqueFacture" method (endpoint "/v1/consulter/historique"), which reports the invoice's current status along with its event history.
	 * All Chorus Pro "factures" API endpoints use POST, including this consultation one (confirmed on the official PISTE API catalog).
	 * @param string $submissionId
	 * @return array|null
	 */
	public function getInvoiceStatus(string $submissionId): ?array
	{
		try {
			return $this->callApi(HTTPMethod::POST, 'cpro/factures/v1/consulter/historique', ['idFacture' => $submissionId]);
		}
		catch (\Throwable $e) {
			$this->logger->error('Chorus Pro status lookup failed: '.$e->getMessage(), ['exception' => $e]);
			return null;
		}
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
	 * Submits the invoice as a PDF file: DEPOT_PDF_API (Factur-X hybrid PDF/A-3) or, if $signed, DEPOT_PDF_SIGNE_API (same file, additionally signed via $pdfSigner). Signing is applied after the Factur-X XML is embedded, not before, since altering a signed PDF would invalidate its signature.
	 * @param InvoiceInterface $invoice
	 * @param string|null $invoiceHtml
	 * @param bool $signed
	 * @return array|null
	 */
	private function submitViaPdfApi(InvoiceInterface $invoice, ?string $invoiceHtml, bool $signed): ?array
	{
		if (null === $invoiceHtml) {
			throw new \RuntimeException('Chorus Pro PDF submission requires the invoice HTML to render the PDF.');
		}

		$tmpDir = sys_get_temp_dir();
		$tmpPdfPath = $tmpDir.'/chorus_pro_'.uniqid('', true).'.pdf';
		$tmpFacturXPath = $tmpDir.'/chorus_pro_facturx_'.uniqid('', true).'.pdf';
		$tmpSignedPath = $tmpDir.'/chorus_pro_signed_'.uniqid('', true).'.pdf';

		try {
			if (!$this->pdfGenerator->generateFile($tmpPdfPath, $invoiceHtml)) {
				throw new \RuntimeException('Chorus Pro PDF submission aborted: failed to generate the invoice PDF.');
			}
			if (null === $this->facturXGenerator->generate($invoice, $tmpPdfPath, $tmpFacturXPath)) {
				throw new \RuntimeException('Chorus Pro PDF submission aborted: failed to generate the Factur-X file.');
			}

			$pdfPath = $tmpFacturXPath;

			if ($signed) {
				if (null === $this->pdfSigner) {
					throw new \RuntimeException('Chorus Pro DEPOT_PDF_SIGNE_API submission aborted: no PDF signer is configured.');
				}
				$this->pdfSigner->sign($tmpFacturXPath, $tmpSignedPath);
				$pdfPath = $tmpSignedPath;
			}

			if (!is_readable($pdfPath) || false === ($fileContent = file_get_contents($pdfPath))) {
				throw new \RuntimeException('Chorus Pro PDF submission aborted: unable to read the generated '.($signed ? 'signed ' : '').'file: '.$pdfPath);
			}

			return $this->callSoumettreFacture([
				'modeDepot' => ($signed ? ChorusProSubmissionMode::DEPOT_PDF_SIGNE_API : ChorusProSubmissionMode::DEPOT_PDF_API)->value,
				'numeroFactureSaisi' => $invoice->getInvoiceNumber(),
				'dateFacture' => $invoice->getDate()->format('Y-m-d'),
				'fichier' => [
					'nomFichier' => ($invoice->getInvoiceNumber() ?: 'invoice').'.pdf',
					'contenuFichier' => base64_encode($fileContent),
				],
			]);
		}
		finally {
			@unlink($tmpPdfPath);
			@unlink($tmpFacturXPath);
			@unlink($tmpSignedPath);
		}
	}

	// ========== Validation ==========

	/**
	 * Checks that the invoice holds everything Chorus Pro requires, whatever the submission mode: document type, supplier and recipient identification, invoice number, currency, at least one product line (each with a label, a positive quantity and a non-negative VAT rate), and the references mandatory for the recipient's invoicing category.
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
		foreach ($invoice->getProductsList() as $index => $product) {
			if (empty($product->getLabel())) {
				return 'product line #'.($index + 1).' has no label.';
			}
			if ($product->getQuantity() <= 0) {
				return 'product line #'.($index + 1).' has an invalid quantity (must be greater than 0).';
			}
			if ($product->getVatRate() < 0) {
				return 'product line #'.($index + 1).' has a negative VAT rate.';
			}
		}

		/** @var ChorusProRecipientInterface&OrganizationInterface $buyer */
		$buyer = $invoice->getBuyer();

		if (empty($buyer->getRegistrationNumber())) {
			return 'the recipient registration number (SIRET) is missing.';
		}
		if (null === ($referenceRequirement = $buyer->getChorusProInvoiceReferenceRequirement())) {
			return 'the recipient\'s invoice reference requirement (ChorusProInvoiceReferenceRequirement) is missing.';
		}
		if (ChorusProInvoiceReferenceRequirement::ENGAGEMENT_REQUIRED === $referenceRequirement && empty($invoice->getCustomerOrderReference())) {
			return 'the ENGAGEMENT_REQUIRED reference requirement requires a customer order reference (engagement number), none was provided.';
		}
		if (ChorusProInvoiceReferenceRequirement::SERVICE_CODE_REQUIRED === $referenceRequirement && empty($buyer->getChorusProServiceCode())) {
			return 'the SERVICE_CODE_REQUIRED reference requirement requires a service code, none was provided.';
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
		/** @var ChorusProRecipientInterface&OrganizationInterface $buyer */
		$buyer = $invoice->getBuyer();

		$vatBreakdown = VatBreakdown::fromInvoice($invoice);

		return [
			'modeDepot' => ChorusProSubmissionMode::SAISIE_API->value,
			'dateFacture' => $invoice->getDate()->format('Y-m-d'),
			'destinataire' => [
				'codeDestinataire' => $buyer->getRegistrationNumber(),
				'codeServiceExecutant' => $buyer->getChorusProServiceCode(),
			],
			'fournisseur' => [
				'idFournisseur' => $this->resolveStructureId($invoice->getSeller()->getRegistrationNumber()),
			],
			'cadreDeFacturation' => [
				'codeCadreFacturation' => self::DEFAULT_INVOICING_FRAMEWORK_CODE,
			],
			'references' => [
				'deviseFacture' => $invoice->getCurrency(),
				'typeFacture' => 'FACTURE',
				'typeTva' => $this->vatType->value,
				'modePaiement' => $this->getPaymentModeCode($invoice->getPaymentMethod()),
				'numeroBonCommande' => $invoice->getCustomerOrderReference(),
			],
			'numeroFactureSaisi' => $invoice->getInvoiceNumber(),
			'lignePoste' => array_map(static fn (InvoiceProductInterface $product, int $index) => [
				'lignePosteNumero' => $index + 1,
				'lignePosteDenomination' => $product->getLabel(),
				'lignePosteQuantite' => $product->getQuantity(),
				'lignePosteUnite' => self::DEFAULT_UNIT_CODE,
				'lignePosteMontantUnitaireHT' => $product->getUnitPrice(),
				'lignePosteTauxTvaManuel' => $product->getVatRate(),
			], $invoice->getProductsList(), array_keys($invoice->getProductsList())),
			'ligneTva' => array_map(static fn (VatBreakdown $line) => [
				'ligneTvaMontantBaseHtParTaux' => $line->baseExclTax,
				'ligneTvaTauxManuel' => $line->rate,
				'ligneTvaMontantTvaParTaux' => $line->vatAmount,
			], $vatBreakdown),
			'montantTotal' => [
				'montantHtTotal' => $invoice->getTotalExclTax(),
				'montantTVA' => $invoice->getTotalVat(),
				'montantTtcTotal' => $invoice->getTotalInclTax(),
				'montantAPayer' => $invoice->getTotalInclTax(),
			],
		];
	}

	/**
	 * Resolves the Chorus Pro internal structure id ("idStructureCPP") for the SIRET of the structure submitting the invoice ("fournisseur.idFournisseur" in the SAISIE_API payload, which Chorus Pro expects as this internal id rather than the SIRET itself).
	 * If $supplierStructureId is configured, it is returned directly, since the submitting structure's SIRET is always the same across invoices and its id can be resolved once and configured instead of being looked up on every submission.
	 * Otherwise it is resolved via the "rechercherStructure" method (endpoint "/v1/rechercher" of the "Structures" API, a separate PISTE product from "Factures") and cached for the lifetime of this instance.
	 * @param string $siret
	 * @return int
	 * @throws \RuntimeException If no structure is found in Chorus Pro for this SIRET
	 */
	private function resolveStructureId(string $siret): int
	{
		if (null !== $this->supplierStructureId) {
			return $this->supplierStructureId;
		}

		if (isset($this->structureIdCache[$siret])) {
			return $this->structureIdCache[$siret];
		}

		$response = $this->callApi(HTTPMethod::POST, 'cpro/structures/v1/rechercher', [
			'structure' => [
				'identifiantStructure' => $siret,
				'typeIdentifiantStructure' => 'SIRET',
			],
		]);

		if (!is_int($id = $response['listeStructures'][0]['idStructureCPP'] ?? null)) {
			throw new \RuntimeException('Unable to resolve the Chorus Pro structure id ("idStructureCPP") for SIRET '.$siret.'.');
		}

		return $this->structureIdCache[$siret] = $id;
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
	 * @throws \RuntimeException If the API answers with an HTTP error status
	 */
	private function callSoumettreFacture(array $payload): ?array
	{
		return $this->callApi(HTTPMethod::POST, 'cpro/factures/v1/soumettre', $payload);
	}

	/**
	 * Calls an authenticated Chorus Pro API endpoint and returns its JSON-decoded response.
	 * The HTTP status is checked here because the API reports business errors (rejected invoice, invalid field, etc.) as an error status with a JSON body, which would otherwise be mistaken for a valid response.
	 * @param HTTPMethod $method
	 * @param string $route The full route, relative to the API domain (e.g. "cpro/factures/v1/soumettre")
	 * @param array $data The request data: query parameters for GET, JSON body for POST
	 * @return array|null The decoded response, or null if the response is not a valid JSON object
	 * @throws \RuntimeException If authentication against PISTE failed, or if the API answers with an HTTP error status (4xx/5xx, the message then contains the status code and the beginning of the response body)
	 */
	private function callApi(HTTPMethod $method, string $route, array $data): ?array
	{
		// Not ambiguous: no request was ever sent to Chorus Pro, so this can never be mistaken for an already-processed submission.
		if (null === ($accessToken = $this->getAccessToken())) {
			throw new \RuntimeException('Chorus Pro API call to "'.$route.'" aborted: could not authenticate against PISTE.');
		}

		$response = $this->requestExecutor->send($method, ($this->sandbox ? self::SANDBOX_API_BASE_URI : self::PRODUCTION_API_BASE_URI).'/'.$route, $data, [
			'Authorization' => 'Bearer '.$accessToken,
			'cpro-account' => base64_encode($this->accountLogin.':'.$this->accountPassword),
		], jsonBody: HTTPMethod::POST === $method);

		if (null === $response) {
			$this->logger->error('Chorus Pro API call to "'.$route.'" failed: no response from the API.');
			return null;
		}

		$body = (string) $response->getBody();

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			throw new \RuntimeException('Chorus Pro API call to "'.$route.'" returned HTTP '.$response->getStatusCode().': '.mb_substr($body, 0, 500));
		}

		$decodedResponse = json_decode($body, true);
		if (!is_array($decodedResponse)) {
			$this->logger->error('Chorus Pro API call to "'.$route.'" failed: the response is not a valid JSON object.');
			return null;
		}

		return $decodedResponse;
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

		$response = $this->requestExecutor->execute(HTTPMethod::POST, $this->sandbox ? self::SANDBOX_OAUTH_URI : self::PRODUCTION_OAUTH_URI, [
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