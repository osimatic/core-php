<?php

namespace Osimatic\Invoice;

use Osimatic\Bank\PaymentMethod;
use Osimatic\Network\HTTPMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Osimatic\Organization\OrganizationInterface;
use Osimatic\Text\PDFGenerator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client for the Chorus Pro API (French public administration e-invoicing platform): authenticates against PISTE and submits invoices via the "soumettreFacture" endpoint, in any of its 3 submission modes.
 * Two distinct sets of credentials are required: the PISTE OAuth2 client ID/secret (identifies the application) and a Chorus Pro account login/password (identifies the Chorus Pro user), sent on every call as the base64-encoded "cpro-account" header. The Chorus Pro account is created separately on the Chorus Pro portal, not on PISTE.
 * Endpoint paths and SAISIE_API payload field names are confirmed against the official PISTE API catalog Swagger schema ("API de Test pour Factures"), except the top-level "idUtilisateurCourant" field (flagged with a TODO in buildSaisieApiPayload()), whose source (a Chorus Pro internal user id) is not yet identified.
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
		private readonly ChorusProSubmissionMode $submissionMode,
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly string $accountLogin,
		private readonly string $accountPassword,
		private readonly bool $enabled = false,
		private readonly string $scope = 'openid',
		private readonly LoggerInterface $logger = new NullLogger(),
		private readonly HTTPRequestExecutor $requestExecutor = new HTTPRequestExecutor(),
		private readonly CiiXmlGenerator $ciiXmlGenerator = new CiiXmlGenerator(),
		private readonly FacturXGenerator $facturXGenerator = new FacturXGenerator(),
		private readonly PDFGenerator $pdfGenerator = new PDFGenerator(),
		private readonly ChorusProVatType $vatType = ChorusProVatType::VAT_ON_DEBIT,
		private readonly ?int $supplierStructureId = null, // the Chorus Pro internal structure id ("idStructureCPP") of the supplier (our own SIRET); if null, resolveStructureId() resolves it via the API instead
		private readonly bool $sandbox = true, // true to target the PISTE sandbox (default), false for production
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

		// Without an identifier the submission cannot be followed up. The invoice may nevertheless have been accepted by Chorus Pro, hence the explicit warning against blindly resubmitting (duplicate).
		if (null === ($submissionId = $response['idFacture'] ?? $response['id'] ?? null)) {
			$this->logger->error('Chorus Pro response contains no invoice identifier: '.mb_substr(json_encode($response), 0, 500));
			return new ChorusProSubmissionResult(ChorusProSubmissionStatus::ERROR, error: 'Chorus Pro response contains no invoice identifier. The invoice may have been received: check in Chorus Pro before submitting it again.');
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
		catch (\RuntimeException $e) {
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
		/** @var ChorusProRecipientInterface&OrganizationInterface $buyer */
		$buyer = $invoice->getBuyer();

		$vatBreakdown = VatBreakdown::fromInvoice($invoice);

		return [
			'modeDepot' => ChorusProSubmissionMode::SAISIE_API->value,
			'dateFacture' => $invoice->getDate()->format('Y-m-d\TH:i:s.v\Z'),
			'destinataire' => [
				'codeDestinataire' => $buyer->getRegistrationNumber(),
				'codeServiceExecutant' => $buyer->getChorusProServiceSiret() ?? $buyer->getRegistrationNumber(),
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
			// "idUtilisateurCourant" (top-level, documented as required) is not set: its source (a Chorus Pro internal user id, distinct from the cpro-account login) is not yet identified.
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
	 * @return int|null
	 */
	private function resolveStructureId(string $siret): ?int
	{
		if (null !== $this->supplierStructureId) {
			return $this->supplierStructureId;
		}

		if (\array_key_exists($siret, $this->structureIdCache)) {
			return $this->structureIdCache[$siret];
		}

		$response = $this->callApi(HTTPMethod::POST, 'cpro/structures/v1/rechercher', [
			'structure' => [
				'identifiantStructure' => $siret,
				'typeIdentifiantStructure' => 'SIRET',
			],
		]);

		return $this->structureIdCache[$siret] = $response['listeStructures'][0]['idStructureCPP'] ?? null;
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
	 * @return array|null The decoded response, or null if authentication failed or the response is not a valid JSON object
	 * @throws \RuntimeException If the API answers with an HTTP error status (4xx/5xx), the message contains the status code and the beginning of the response body
	 */
	private function callApi(HTTPMethod $method, string $route, array $data): ?array
	{
		if (null === ($accessToken = $this->getAccessToken())) {
			$this->logger->error('Chorus Pro API call to "'.$route.'" aborted: could not authenticate against PISTE.');
			return null;
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