<?php

namespace Osimatic\Bank;

use Osimatic\Invoice\BillingAddressInterface;
use Osimatic\Network\HTTPClient;
use Osimatic\Network\HTTPMethod;
use Osimatic\Network\HTTPRequestExecutor;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PayBox payment gateway integration class
 * Handles credit card payments via the PayBox platform (Verifone)
 * Supports both PayBox Direct (server-to-server) and PayBox System (hosted payment form) methods
 * Includes support for 3D Secure authentication, recurring payments (subscribers), and various transaction types
 * @author Benoit Guiraudou <guiraudou@osimatic.com>
 */
class PayBox
{
	private const string URL_PAIEMENT_TEST = 'https://preprod-ppps.paybox.com/PPPS.php';
	private const string URL_PAIEMENT = 'https://ppps.paybox.com/PPPS.php';
	private const string URL_PAIEMENT_SECOURS = 'https://ppps1.paybox.com/PPPS.php';

	private const string URL_FORM_TEST = 'https://preprod-tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi';
	private const string URL_FORM = 'https://tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi';
	private const string URL_FORM_SECOURS = 'https://tpeweb1.paybox.com/cgi/MYchoix_pagepaiement.cgi';

	public const int DEFAULT_FORM_TIMEOUT = 1800;

	/**
	 * PayBox API version to use for payment processing
	 * Determines which PayBox API version (Direct, Direct Plus, etc.) will be used
	 * @var PayBoxVersion
	 */
	private PayBoxVersion $version = PayBoxVersion::PAYBOX_DIRECT_PLUS;

	/**
	 * Language/locale code for the payment form
	 * Controls the language displayed on the PayBox hosted payment page
	 * @var string
	 */
	private string $locale = 'FR';

	/**
	 * Test mode flag
	 * When true, uses PayBox test credentials and test URLs for development/testing
	 * @var bool
	 */
	private bool $isTest = false;

	/**
	 * Form mode flag
	 * When true, uses PayBox System (hosted form), otherwise uses PayBox Direct (HTTP API)
	 * @var bool
	 */
	private bool $useForm = false;


	/**
	 * Custom date and time for the transaction request
	 * If not set, the current date/time is automatically used
	 * @var \DateTime|null
	 */
	private ?\DateTime $date = null;

	/**
	 * Type of bank card operation to perform (authorization, capture, refund, etc.)
	 * @var BankCardOperation
	 */
	private BankCardOperation $bankCardOperation = BankCardOperation::AUTHORIZATION_AND_DEBIT;

	/**
	 * Unique identifier for the request that allows avoiding confusion in case of multiple simultaneous requests
	 * Auto-generated if not set, used as PayBox NUMQUESTION parameter
	 * @var int|null
	 */
	private ?int $numQuestion = null;

	/**
	 * Transaction amount in major currency units (e.g., 10.50 for 10.50 EUR)
	 * @var float
	 */
	private float $montant = 0.;

	/**
	 * Currency code for the transaction (ISO 4217 format)
	 * @var string
	 */
	private string $devise = 'EUR';

	/**
	 * Merchant order reference number (free field, max 250 characters)
	 * Allows the merchant to link their platform to PayBox using a reference number
	 * @var string|null
	 */
	private ?string $reference = null;

	/**
	 * Subscriber reference number for recurring payments
	 * Token allowing to clearly identify the subscriber (profile) for the transaction
	 * @var string|null
	 */
	private ?string $subscriberRef = null;

	/**
	 * Customer email address
	 * Required for PayBox System (hosted form) payments
	 * @var string|null
	 */
	private ?string $porteurEmail = null;

	/**
	 * Customer's credit card number (PAN) or subscriber token for recurring payments
	 * For direct payments: card number without spaces, left-aligned, max 19 digits
	 * For subscriber payments: token reference returned from subscriber registration
	 * @var string|null
	 */
	private ?string $porteur = null;

	/**
	 * Credit card expiration date
	 * Used for PayBox Direct transactions to validate the card
	 * @var \DateTime|null
	 */
	private ?\DateTime $expirationDate = null;

	/**
	 * Card Verification Value (CVV/CVC) security code
	 * 3-4 digit security code printed on the back of the card
	 * @var string|null
	 */
	private ?string $cvv = null;

	/**
	 * Transaction call origin code
	 * Informs the acquiring bank how the transaction was initiated and how the card entry was performed
	 * (e.g., internet payment, telephone order, mail order, recurring payment)
	 * @var BankCardCallOrigin|null
	 */
	private ?BankCardCallOrigin $activite = null;

	/**
	 * Unique archiving reference for dispute management
	 * Transmitted to the acquiring bank during settlement
	 * Allows merchants to request additional information in case of disputes
	 * @var string|null
	 */
	private ?string $archivage = null;

	/**
	 * Deferred settlement period in days
	 * Number of days to postpone the fund capture after authorization (typically 0-7 days)
	 * @var int|null
	 */
	private ?int $differe = null;

	/**
	 * 3D Secure V2 authentication enablement flag
	 * When enabled, requires shopping cart and billing address for enhanced security
	 * @var bool
	 */
	private bool $is3DSecureV2 = false;

	/**
	 * CSS class name for the payment form element
	 * Applied to the <form> HTML tag in PayBox System hosted forms
	 * @var string|null
	 */
	private ?string $formCssClass = null;

	/**
	 * CSS class name for the submit button
	 * Applied to the submit button in PayBox System hosted forms
	 * @var string|null
	 */
	private ?string $buttonCssClass = null;

	/**
	 * Submit button text/label
	 * Displayed on the submit button in PayBox System hosted forms
	 * @var string|null
	 */
	private ?string $buttonText = null;

	/**
	 * Return URL for successful payments (PBX_EFFECTUE parameter)
	 * Customer is redirected to this URL after successful payment completion
	 * @var string|null
	 */
	private ?string $urlResponseOk = null;

	/**
	 * Return URL for refused payments (PBX_REFUSE parameter)
	 * Customer is redirected to this URL when payment is declined or rejected
	 * @var string|null
	 */
	private ?string $urlResponseRefused = null;

	/**
	 * Return URL for canceled payments (PBX_ANNULE parameter)
	 * Customer is redirected to this URL if they cancel the payment process
	 * @var string|null
	 */
	private ?string $urlResponseCanceled = null;

	/**
	 * Return URL for pending/waiting payments (PBX_ATTENTE parameter)
	 * Customer is redirected to this URL when payment status is pending/waiting
	 * @var string|null
	 */
	private ?string $urlResponseWaiting = null;

	/**
	 * IPN (Instant Payment Notification) callback URL (PBX_REPONDRE_A parameter)
	 * PayBox sends server-to-server payment result notifications to this URL
	 * @var string|null
	 */
	private ?string $urlIpn = null;


	/**
	 * Call number returned by PayBox after successful transaction processing
	 * 10-digit reference number from PayBox/Verifone, required for subsequent operations like capture
	 * @var int|null
	 */
	private ?int $numAppel = null;

	/**
	 * Transaction number returned by PayBox after successful processing
	 * 10-digit unique transaction identifier from PayBox/Verifone, required for subsequent operations
	 * @var int|null
	 */
	private ?int $numTransaction = null;

	/**
	 * Authorization number obtained via telephone from the acquiring bank
	 * Used when manual authorization was obtained by phone call instead of automatic authorization
	 * @var string|null
	 */
	private ?string $autorisation = null;

	/**
	 * ISO country code of the card-issuing bank
	 * Returned by PayBox to indicate where the card was issued
	 * @var string|null
	 */
	private ?string $cardCountryCode = null;

	/**
	 * Shopping cart data for 3D Secure V2 authentication
	 * Contains cart items and quantities, required for 3DS2 compliance
	 * @var ShoppingCartInterface|null
	 */
	private ?ShoppingCartInterface $shoppingCart = null;

	/**
	 * Customer billing address for 3D Secure V2 authentication
	 * Contains customer address details, required for 3DS2 compliance
	 * @var BillingAddressInterface|null
	 */
	private ?BillingAddressInterface $billingAddress = null;

	/**
	 * PayBox System form expiration timeout in seconds
	 * Defines how long the generated payment form remains valid before expiring (default: 1800s / 30 minutes)
	 * @var int
	 */
	private int $formTimeout = self::DEFAULT_FORM_TIMEOUT;

	/** HTTP request executor for making API calls */
	private HTTPRequestExecutor $requestExecutor;

	/**
	 * Visa card response codes mapping from PayBox/Verifone
	 * Maps 5-digit Visa response codes to French error messages as specified by PayBox API
	 * Used to interpret card issuer response codes in the 00100-00199 range
	 * @var array<string, string>
	 */
	private static array $visaResponseCodes = [
		'00100' => 'Transaction approuvée ou traitée avec succès',
		'00101' => 'Contacter l’émetteur de carte',
		'00102' => 'Contacter l’émetteur de carte',
		'00103' => 'Commerçant invalide',
		'00104' => 'Conserver la carte',
		'00105' => 'Ne pas honorer',
		'00107' => 'Conserver la carte, conditions spéciales',
		'00108' => 'Approuver après identification du porteur',
		'00112' => 'Transaction invalide',
		'00113' => 'Montant invalide',
		'00114' => 'Numéro de porteur invalide',
		'00115' => 'Emetteur de carte inconnu',
		'00117' => 'Annulation client',
		'00119' => 'Répéter la transaction ultérieurement',
		'00120' => 'Réponse erronée (erreur dans le domaine serveur)',
		'00124' => 'Mise à jour de fichier non supportée',
		'00125' => 'Impossible de localiser l’enregistrement dans le fichier',
		'00126' => 'Enregistrement dupliqué, ancien enregistrement remplacé',
		'00127' => 'Erreur en « edit » sur champ de mise à jour fichier',
		'00128' => 'Accès interdit au fichier',
		'00129' => 'Mise à jour de fichier impossible',
		'00130' => 'Erreur de format',
		'00131' => 'Identifiant de l’organisme acquéreur inconnu.',
		'00133' => 'Date de validité de la carte dépassée.',
		'00134' => 'Suspicion de fraude.',
		'00138' => 'Nombre d’essais code confidentiel dépassé',
		'00141' => 'Carte perdue',
		'00143' => 'Carte volée',
		'00151' => 'Provision insuffisante ou crédit dépassé',
		'00154' => 'Date de validité de la carte dépassée',
		'00155' => 'Code confidentiel erroné',
		'00156' => 'Carte absente du fichier',
		'00157' => 'Transaction non permise à ce porteur',
		'00158' => 'Transaction interdite au terminal',
		'00159' => 'Suspicion de fraude',
		'00160' => 'L’accepteur de carte doit contacter l’acquéreur',
		'00161' => 'Dépasse la limite du montant de retrait',
		'00163' => 'Règles de sécurité non respectées',
		'00168' => 'Réponse non parvenue ou reçue trop tard',
		'00175' => 'Nombre d’essais code confidentiel dépassé',
		'00176' => 'Porteur déjà en opposition, ancien enregistrement conservé',
		'00189' => 'Echec de l’authentification',
		'00190' => 'Arrêt momentané du système',
		'00191' => 'Emetteur de cartes inaccessible',
		'00194' => 'Demande dupliquée',
		'00196' => 'Mauvais fonctionnement du système',
		'00197' => 'Echéance de la temporisation de surveillance globale',
		'00198' => 'Serveur inaccessible (positionné par le serveur).',
		'00199' => 'Incident domaine initiateur.',
	];

	/**
	 * PayBox system response codes mapping
	 * Maps 5-digit PayBox response codes to French error messages as specified by PayBox API
	 * Used to interpret PayBox platform errors in the 00000-00099 range
	 * Code '00000' indicates successful operation
	 * @var array<string, string>
	 */
	private static array $responseCodes = [
		'00000' => 'Opération réussie',
		'00001' => 'Echec de connexion au centre d’autorisation',
		'00002' => 'Une erreur de cohérence est survenue',
		'00003' => 'Erreur Paybox',
		'00004' => 'Numéro de porteur ou cryptogramme visuel invalide',
		'00005' => 'Numéro de question invalide',
		'00006' => 'Accès refusé ou site/rang/identifiant incorrect',
		'00007' => 'Date invalide',
		'00008' => 'Date de fin de validité incorrecte',
		'00009' => 'Type d’opération invalide.',
		'00010' => 'Devise inconnue',
		'00011' => 'Montant incorrect',
		'00012' => 'Référence commande invalide',
		'00013' => 'Cette version n’est plus soutenue',
		'00014' => 'Trame reçue incohérente',
		'00015' => 'Erreur d’accès aux données précédemment référencées.',
		'00016' => 'Abonné déjà existant (inscription nouvel abonné)',
		'00017' => 'Abonné inexistant.',
		'00018' => 'Transaction non trouvée',
		'00020' => 'Cryptogramme visuel non présent',
		'00021' => 'Carte non autorisée',
		'00022' => 'Plafond atteint',
		'00023' => 'Porteur déjà passé aujourd’hui',
		'00024' => 'Code pays filtré pour ce commerçant',
		'00026' => 'Code activité incorrect',
		'00040' => 'Porteur enrôlé mais non authentifié',
		'00097' => 'Timeout de connexion atteint',
		'00098' => 'Erreur de connexion interne',
		'00099' => 'Incohérence entre la question et la réponse. Refaire une nouvelle tentative ultérieurement',
	];

	/**
	 * Initialize PayBox payment gateway integration
	 * Configures credentials and connection parameters for PayBox Direct and PayBox System
	 * @param string $siteNumber The 7-digit merchant TPE number provided by PayBox
	 * @param string $rang The 2-3 digit merchant rank number provided by PayBox
	 * @param string $identifier The 1-9 digit identifier for PayBox System (PBX_IDENTIFIANT parameter)
	 * @param string $httpPassword The 8-10 character key for PayBox Direct (CLE parameter)
	 * @param string $secretKey The 128-character hexadecimal key for PayBox System HMAC generation
	 * @param LoggerInterface $logger The PSR-3 logger instance for error and debugging (default: NullLogger)
	 * @param ClientInterface $httpClient The PSR-18 HTTP client instance used for making API requests (default: HTTPClient)
	 */
	public function __construct(
		/**
		 * The 7-digit merchant TPE (Terminal de Paiement Électronique) number provided by PayBox
		 * Required for both PayBox Direct and PayBox System methods
		 */
		private string $siteNumber = '',

		/**
		 * The 2-3 digit merchant rank number provided by PayBox
		 * Allows merchants to have multiple configurations under the same site number
		 */
		private string $rang = '',

		/**
		 * Unique identifier provided by Paybox, used for Paybox System only ("PBX_IDENTIFIANT" parameter)
		 */
		private string $identifier = '',

		/**
		 * Unique key provided by Paybox and used for Paybox Direct only ("CLE" parameter)
		 */
		private string $httpPassword = '',

		/**
		 * Unique key generated in back-office and used for Paybox System only (HMAC generation)
		 */
		private string $secretKey = '',

		private readonly LoggerInterface $logger=new NullLogger(),
		ClientInterface $httpClient = new HTTPClient(),
	)
	{
		$this->requestExecutor = new HTTPRequestExecutor($httpClient, $logger);
	}

	/**
	 * Reset all transaction-specific data
	 * Clears transaction details, card information, and response data while keeping configuration
	 * @return self Returns this instance for method chaining
	 */
	public function reset(): self
	{
		$this->numQuestion = null;
		$this->date = null;
		$this->reference = null;
		$this->subscriberRef = null;
		$this->porteurEmail = null;
		$this->porteur = null;
		$this->expirationDate = null;
		$this->cvv = null;

		$this->numAppel = null;
		$this->numTransaction = null;
		$this->autorisation = null;
		$this->shoppingCart = null;
		$this->billingAddress = null;

		return $this;
	}

	/**
	 * Execute a new payment transaction
	 * Uses the currently configured operation type (authorization, debit, etc.)
	 * @return PayBoxResponse|null The payment response, or null on failure
	 */
	public function newPayment(): ?PayBoxResponse
	{
		if (false === ($result = $this->doRequest())) {
			return null;
		}
		return $result;
	}

	/**
	 * Perform an authorization-only operation
	 * Validates the card and reserves funds without capturing them
	 * @return PayBoxResponse|null The authorization response, or null on failure
	 */
	public function doAuthorization(): ?PayBoxResponse
	{
		$this->bankCardOperation = BankCardOperation::AUTHORIZATION_ONLY;
		return $this->newPayment();
	}

	/**
	 * Capture previously authorized funds
	 * Finalizes a transaction that was previously authorized
	 * Requires numAppel and numTransaction to be set from the authorization
	 * @return PayBoxResponse|null The debit response, or null on failure
	 */
	public function doDebit(): ?PayBoxResponse
	{
		$this->bankCardOperation = BankCardOperation::DEBIT;
		return $this->newPayment();
	}

	/**
	 * Perform authorization and immediate capture in one operation
	 * Validates the card and captures funds immediately
	 * @return PayBoxResponse|null The payment response, or null on failure
	 */
	public function doAuthorizationAndDebit(): ?PayBoxResponse
	{
		$this->bankCardOperation = BankCardOperation::AUTHORIZATION_AND_DEBIT;
		return $this->newPayment();
	}

	/**
	 * Register a new subscriber for recurring payments
	 * Stores card information for future transactions without immediate charge
	 * Returns a subscriber reference (token) for future use
	 * @return PayBoxResponse|null The registration response with subscriber token, or null on failure
	 */
	public function addSubscriber(): ?PayBoxResponse
	{
		$this->bankCardOperation = BankCardOperation::REGISTER_SUBSCRIBER;
		return $this->newPayment();
	}

	/**
	 * Delete a subscriber from the system
	 * Removes stored card information for a subscriber
	 * @return PayBoxResponse|null The deletion response, or null on failure
	 */
	public function deleteSubscriber(): ?PayBoxResponse
	{
		$this->bankCardOperation = BankCardOperation::DELETE_SUBSCRIBER;
		return $this->newPayment();
	}



	/**
	 * Generate HTML form for subscriber registration
	 * Creates a PayBox System form that redirects to PayBox hosted page for card registration
	 * @return string|null HTML form markup, or null on validation failure
	 */
	public function getFormSubscriberRegister(): ?string
	{
		$this->bankCardOperation = BankCardOperation::REGISTER_SUBSCRIBER;
		$this->useForm = true;

		if (false === ($result = $this->doRequest())) {
			return null;
		}
		return $result;
	}

	/**
	 * Generate HTML payment form
	 * Creates a PayBox System form that redirects to PayBox hosted payment page
	 * @return string|null HTML form markup, or null on validation failure
	 */
	public function getForm(): ?string
	{
		$this->useForm = true;

		if (false === ($result = $this->doRequest())) {
			return null;
		}
		return $result;
	}



	// ========== Set Request ==========

	/**
	 * Set PayBox API version
	 * @param PayBoxVersion $version The PayBox version to use (Direct, Direct Plus, etc.)
	 * @return self Returns this instance for method chaining
	 */
	public function setVersion(PayBoxVersion $version): self
	{
		$this->version = $version;

		return $this;
	}

	/**
	 * Set merchant site number
	 * The 7-digit TPE number provided by PayBox
	 * @param string $siteNumber The site/TPE number
	 * @return self Returns this instance for method chaining
	 */
	public function setSiteNumber(string $siteNumber): self
	{
		$this->siteNumber = $siteNumber;

		return $this;
	}

	/**
	 * Set merchant rank number
	 * The 2-3 digit rank number provided by PayBox
	 * @param string $rang The rank number
	 * @return self Returns this instance for method chaining
	 */
	public function setRang(string $rang): self
	{
		$this->rang = $rang;

		return $this;
	}

	/**
	 * Set PayBox identifier
	 * Used for PayBox System only (1-9 digits identifier)
	 * @param string $identifier The PayBox identifier
	 * @return self Returns this instance for method chaining
	 */
	public function setIdentifier(string $identifier): self
	{
		$this->identifier = $identifier;

		return $this;
	}

	/**
	 * Set HTTP password key
	 * Used for PayBox Direct only (8-10 characters key)
	 * @param string $httpPassword The HTTP password key
	 * @return self Returns this instance for method chaining
	 */
	public function setHttpPassword(string $httpPassword): self
	{
		$this->httpPassword = $httpPassword;

		return $this;
	}

	/**
	 * Set HMAC secret key
	 * Used for PayBox System only (128-character hexadecimal key generated in back-office)
	 * @param string $secretKey The HMAC secret key
	 * @return self Returns this instance for method chaining
	 */
	public function setSecretKey(string $secretKey): self
	{
		$this->secretKey = $secretKey;

		return $this;
	}

	/**
	 * Set payment form language locale
	 * @param string $locale Language code (e.g., 'FR', 'EN', 'ES')
	 * @return self Returns this instance for method chaining
	 */
	public function setLocale(string $locale): self
	{
		$this->locale = $locale;

		return $this;
	}

	/**
	 * Enable or disable test mode
	 * In test mode, uses PayBox test credentials and test URLs
	 * @param bool $isTest True to enable test mode, false for production
	 * @return self Returns this instance for method chaining
	 */
	public function setIsTest(bool $isTest): self
	{
		$this->isTest = $isTest;

		return $this;
	}



	/**
	 * Set custom transaction date/time
	 * If not set, current date/time will be used
	 * @param \DateTime|null $date The transaction date/time
	 * @return self Returns this instance for method chaining
	 */
	public function setDate(?\DateTime $date): self
	{
		$this->date = $date;

		return $this;
	}

	/**
	 * Set the type of bank card operation
	 * @param BankCardOperation $bankCardOperation The operation type (authorization, debit, etc.)
	 * @return self Returns this instance for method chaining
	 */
	public function setBankCardOperation(BankCardOperation $bankCardOperation): self
	{
		$this->bankCardOperation = $bankCardOperation;

		return $this;
	}

	/**
	 * Set unique question number for the request
	 * Allows avoiding confusion in case of multiple simultaneous requests
	 * If not set, will be auto-generated
	 * @param int|null $questionNumber The unique question number
	 * @return self Returns this instance for method chaining
	 */
	public function setQuestionNumber(?int $questionNumber): self
	{
		$this->numQuestion = $questionNumber;

		return $this;
	}

	/**
	 * Set transaction total amount
	 * @param float $total Amount in currency units (e.g., 10.50 for 10.50 EUR)
	 * @return self Returns this instance for method chaining
	 */
	public function setTotal(float $total): self
	{
		$this->montant = $total;

		return $this;
	}

	/**
	 * Set transaction amount (alias for setTotal)
	 * @param float $transactionAmount Amount in currency units
	 * @return self Returns this instance for method chaining
	 */
	public function setTransactionAmount(float $transactionAmount): self
	{
		return $this->setTotal($transactionAmount);
	}

	/**
	 * Set transaction currency
	 * @param string $currency ISO 4217 currency code (e.g., 'EUR', 'USD')
	 * @return self Returns this instance for method chaining
	 */
	public function setCurrency(string $currency): self
	{
		$this->devise = $currency;

		return $this;
	}

	/**
	 * Set merchant order reference
	 * Free field to link your platform to PayBox (max 250 characters)
	 * @param string|null $reference Your order reference
	 * @return self Returns this instance for method chaining
	 */
	public function setReference(?string $reference): self
	{
		$this->reference = $reference;

		return $this;
	}

	/**
	 * Set subscriber reference for recurring payments
	 * The subscriber token/reference obtained from a previous registration
	 * @param string|null $subscriberRef The subscriber reference
	 * @return self Returns this instance for method chaining
	 */
	public function setSubscriberReference(?string $subscriberRef): self
	{
		$this->subscriberRef = $subscriberRef;

		return $this;
	}

	/**
	 * Set customer email address
	 * Required for PayBox System (hosted form) payments
	 * @param string|null $porteurEmail The customer's email address
	 * @return self Returns this instance for method chaining
	 */
	public function setCustomerEmail(?string $porteurEmail): self
	{
		$this->porteurEmail = $porteurEmail;

		return $this;
	}

	/**
	 * Set credit card number (PAN)
	 * Used for PayBox Direct payments (without spaces, left aligned, max 19 digits)
	 * @param string|null $creditCardNumber The card number
	 * @return self Returns this instance for method chaining
	 */
	public function setCreditCardNumber(?string $creditCardNumber): self
	{
		$this->porteur = $creditCardNumber;

		return $this;
	}

	/**
	 * Set credit card token for subscriber payments
	 * Used instead of card number when charging a registered subscriber
	 * @param string|null $creditCardToken The subscriber's card token
	 * @return self Returns this instance for method chaining
	 */
	public function setCreditCardToken(?string $creditCardToken): self
	{
		$this->porteur = $creditCardToken;

		return $this;
	}

	/**
	 * Set card expiration date
	 * @param \DateTime|null $expirationDate The expiration date
	 * @return self Returns this instance for method chaining
	 */
	public function setExpirationDate(?\DateTime $expirationDate): self
	{
		$this->expirationDate = $expirationDate;

		return $this;
	}

	/**
	 * Set card CVV/CVC code
	 * The 3-4 digit security code on the back of the card
	 * @param string|null $cvv The CVV code
	 * @return self Returns this instance for method chaining
	 */
	public function setCvc(?string $cvv): self
	{
		$this->cvv = $cvv;

		return $this;
	}

	/**
	 * Set call origin/activity code
	 * Informs the acquirer how the transaction was initiated
	 * @param BankCardCallOrigin|null $callOrigin The call origin type
	 * @return self Returns this instance for method chaining
	 */
	public function setCallOrigin(?BankCardCallOrigin $callOrigin): self
	{
		$this->activite = $callOrigin;

		return $this;
	}

	/**
	 * Set archiving reference
	 * Unique reference transmitted to acquirer for dispute inquiries
	 * @param string|null $archivingReference The archiving reference
	 * @return self Returns this instance for method chaining
	 */
	public function setArchivingReference(?string $archivingReference): self
	{
		$this->archivage = $archivingReference;

		return $this;
	}

	/**
	 * Set number of days to postpone settlement
	 * Delays the fund capture by specified number of days
	 * @param int|null $numberOfDays Number of days (typically 0-7)
	 * @return self Returns this instance for method chaining
	 */
	public function setNumberOfDaysForPostponedSettlement(?int $numberOfDays): self
	{
		$this->differe = $numberOfDays;

		return $this;
	}

	/**
	 * Set call number from previous authorization
	 * Required for capture operations after authorization-only
	 * @param int $callNumber The call number from authorization response
	 * @return self Returns this instance for method chaining
	 */
	public function setCallNumber(int $callNumber): self
	{
		$this->numAppel = $callNumber;

		return $this;
	}

	/**
	 * Set transaction number from previous authorization
	 * Required for capture operations after authorization-only
	 * @param int $transactionNumber The transaction number from authorization response
	 * @return self Returns this instance for method chaining
	 */
	public function setTransactionNumber(int $transactionNumber): self
	{
		$this->numTransaction = $transactionNumber;

		return $this;
	}

	/**
	 * Set authorization number obtained by telephone
	 * Used when authorization was obtained via phone call to acquirer
	 * @param string $authorizationNumber The phone authorization number
	 * @return self Returns this instance for method chaining
	 */
	public function setAuthorizationNumber(string $authorizationNumber): self
	{
		$this->autorisation = $authorizationNumber;

		return $this;
	}

	/**
	 * Set CSS class for payment form
	 * Applied to the <form> HTML tag
	 * @param string|null $formCssClass CSS class name
	 * @return self Returns this instance for method chaining
	 */
	public function setFormCssClass(?string $formCssClass): self
	{
		$this->formCssClass = $formCssClass;

		return $this;
	}

	/**
	 * Set CSS class for submit button
	 * Applied to the submit button in payment form
	 * @param string|null $buttonCssClass CSS class name
	 * @return self Returns this instance for method chaining
	 */
	public function setButtonCssClass(?string $buttonCssClass): self
	{
		$this->buttonCssClass = $buttonCssClass;

		return $this;
	}

	/**
	 * Set text for submit button
	 * The button label in the payment form
	 * @param string|null $buttonText Button text
	 * @return self Returns this instance for method chaining
	 */
	public function setButtonText(?string $buttonText): self
	{
		$this->buttonText = $buttonText;

		return $this;
	}

	/**
	 * Set return URL for successful payment
	 * Customer is redirected here after successful payment
	 * @param string|null $urlResponseOk The success return URL
	 * @return self Returns this instance for method chaining
	 */
	public function setUrlResponseOk(?string $urlResponseOk): self
	{
		$this->urlResponseOk = $urlResponseOk;

		return $this;
	}

	/**
	 * Set return URL for refused payment
	 * Customer is redirected here when payment is refused
	 * @param string|null $urlResponseRefused The refusal return URL
	 * @return self Returns this instance for method chaining
	 */
	public function setUrlResponseRefused(?string $urlResponseRefused): self
	{
		$this->urlResponseRefused = $urlResponseRefused;

		return $this;
	}

	/**
	 * Set return URL for canceled payment
	 * Customer is redirected here if they cancel the payment
	 * @param string|null $urlResponseCanceled The cancellation return URL
	 * @return self Returns this instance for method chaining
	 */
	public function setUrlResponseCanceled(?string $urlResponseCanceled): self
	{
		$this->urlResponseCanceled = $urlResponseCanceled;

		return $this;
	}

	/**
	 * Set return URL for pending/waiting payment
	 * Customer is redirected here when payment is in waiting state
	 * @param string|null $urlResponseWaiting The waiting return URL
	 * @return self Returns this instance for method chaining
	 */
	public function setUrlResponseWaiting(?string $urlResponseWaiting): self
	{
		$this->urlResponseWaiting = $urlResponseWaiting;

		return $this;
	}

	/**
	 * Set IPN (Instant Payment Notification) callback URL
	 * PayBox will send payment result to this URL (server-to-server)
	 * @param string|null $urlIpn The IPN callback URL
	 * @return self Returns this instance for method chaining
	 */
	public function setUrlIpn(?string $urlIpn): self
	{
		$this->urlIpn = $urlIpn;

		return $this;
	}

	/**
	 * Set operation to authorization-only mode
	 * Shortcut method to configure authorization without immediate capture
	 * @return self Returns this instance for method chaining
	 */
	public function setAuthorizationOnly(): self
	{
		$this->bankCardOperation = BankCardOperation::AUTHORIZATION_ONLY;

		return $this;
	}

	/**
	 * Check if 3D Secure V2 is enabled
	 * @return bool True if 3D Secure V2 is enabled
	 */
	public function is3DSecureV2(): bool
	{
		return $this->is3DSecureV2;
	}

	/**
	 * Enable 3D Secure V2 authentication
	 * Requires shopping cart and billing address to be set
	 * @return self Returns this instance for method chaining
	 */
	public function set3DSecureV2(): self
	{
		$this->is3DSecureV2 = true;

		return $this;
	}

	/**
	 * Get form timeout duration
	 * @return int Timeout in seconds
	 */
	public function getFormTimeout(): int
	{
		return $this->formTimeout;
	}

	/**
	 * Set payment form expiration timeout
	 * How long the PayBox System form remains valid before expiring
	 * @param int $formTimeout Timeout in seconds (default: 1800)
	 * @return self Returns this instance for method chaining
	 */
	public function setFormTimeout(int $formTimeout): self
	{
		$this->formTimeout = $formTimeout;

		return $this;
	}

	/**
	 * Get billing address
	 * @return BillingAddressInterface|null The billing address
	 */
	private function getBillingAddress(): ?BillingAddressInterface
	{
	   return $this->billingAddress;
	}

	/**
	 * Set billing address for 3D Secure V2
	 * Required when using 3D Secure V2 authentication
	 * @param BillingAddressInterface $billingAddress The customer's billing address
	 * @return self Returns this instance for method chaining
	 */
	 public function setBillingAddress(BillingAddressInterface $billingAddress): self
	{
		$this->billingAddress = $billingAddress;

		return $this;
	}

	/**
	 * Get shopping cart
	 * @return ShoppingCartInterface|null The shopping cart
	 */
	private function getShoppingCart(): ?ShoppingCartInterface
	{
	   return $this->shoppingCart;
	}

	/**
	 * Set shopping cart for 3D Secure V2
	 * Required when using 3D Secure V2 authentication
	 * @param ShoppingCartInterface $shoppingCart The shopping cart with items
	 * @return self Returns this instance for method chaining
	 */
	 public function setShoppingCart(ShoppingCartInterface $shoppingCart): self
	{
		$this->shoppingCart = $shoppingCart;

		return $this;
	}


	// ========== Get Response ==========

	/**
	 * Get call number from last transaction
	 * @return int|null The call number returned by PayBox
	 */
	public function getCallNumber(): ?int
	{
		return $this->numAppel;
	}

	/**
	 * Get transaction number from last transaction
	 * @return int|null The transaction number returned by PayBox
	 */
	public function getTransactionNumber(): ?int
	{
		return $this->numTransaction;
	}

	/**
	 * Get authorization number from last transaction
	 * @return string|null The authorization number
	 */
	public function getAuthorizationNumber(): ?string
	{
		return $this->autorisation;
	}

	/**
	 * Get card country code from last transaction
	 * @return string|null ISO country code of card issuance
	 */
	public function getCardCountryCode(): ?string
	{
		return $this->cardCountryCode;
	}


	// ========== Private function ==========


	/**
	 * Generate billing address XML for 3D Secure V2 authentication
	 * Converts the billing address into PayBox XML format required for 3D Secure 2.0
	 * Used in the PBX_BILLING parameter when submitting payment forms
	 * @return string|null The billing address XML string, or null if no billing address is set
	 */
	private function getBillingAddressAsXml(): ?string
	{
		if (null === ($billingAddress = $this->getBillingAddress())) {
			return null;
		}

		return '<?xml version="1.0" encoding="utf-8"?><Billing>'
			.'<Address><FirstName>'.($billingAddress->getFirstName() ?? '-').'</FirstName><LastName>'.($billingAddress->getLastName() ?? $billingAddress->getCompanyName()).'</LastName><Address1>'.$billingAddress->getStreet().'</Address1>'
			.'<Address2>'.$billingAddress->getStreet2().'</Address2><ZipCode>'.$billingAddress->getZipCode().'</ZipCode><City>'.$billingAddress->getCity().'</City><CountryCode>'.\Osimatic\Location\Country::getCountryNumericCodeFromCountryCode($billingAddress->getCountryCode()).'</CountryCode></Address>'
			.'</Billing>';
	}

	/**
	 * Generate shopping cart XML for 3D Secure V2 authentication
	 * Converts the shopping cart into PayBox XML format required for 3D Secure 2.0
	 * Used in the PBX_SHOPPINGCART parameter when submitting payment forms
	 * @return string|null The shopping cart XML string with total quantity, or null if no shopping cart is set
	 */
	private function getShoppingCartAsXml(): ?string
	{
		if (null === ($shoppingCart = $this->getShoppingCart())) {
			return null;
		}

		return '<?xml version="1.0" encoding="utf-8"?><shoppingcart><total><totalQuantity>'.$shoppingCart->getTotalQuantity().'</totalQuantity></total></shoppingcart>';
	}


	/**
	 * Execute payment request and validate all parameters
	 * Central method that orchestrates the payment flow: validates configuration, prepares data,
	 * and routes to either PayBox System (form generation) or PayBox Direct (HTTP request)
	 * Handles test mode configuration, validates credentials, amounts, card data, and subscriber references
	 * @return PayBoxResponse|bool|string|null Returns PayBoxResponse for Direct, HTML string for System, false on validation error, null on failure
	 */
	private function doRequest(): PayBoxResponse|bool|string|null
	{
		if ($this->isTest) {
			$this->version = PayBoxVersion::PAYBOX_DIRECT_PLUS;
			$this->siteNumber = '1999888';
			$this->identifier = '109518543';
			$this->httpPassword = '1999888I';
			$this->secretKey = '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF';
			$this->rang = '63';
		}

		if (empty($this->siteNumber) || strlen($this->siteNumber) !== 7) {
			$this->logger?->error('Invalid TPE number: ' . $this->siteNumber);
			return false;
		}

		if (empty($this->rang) || strlen($this->rang) < 2 || strlen($this->rang) > 3) {
			$this->logger?->error('Invalid rank number: ' . $this->rang);
			return false;
		}

		if ($this->useForm) {
			if (!$this->_checkIdentifier()) {
				$this->logger?->error('Invalid PayBox identifier: ' . $this->identifier);
				return false;
			}

			if (!$this->_checkSecretKey()) {
				$this->logger?->error('Invalid secret key: ' . $this->secretKey);
				return false;
			}
		}
		else {
			if (!$this->_checkHttpPassword()) {
				$this->logger?->error('Invalid PayBox HTTP password: ' . $this->httpPassword);
				return false;
			}
		}

		$this->numQuestion = (empty($this->numQuestion) ? date('His') . random_int(1, 999) : $this->numQuestion);
		if (!is_numeric($this->numQuestion)) {
			$this->logger?->error('Invalid question number: ' . $this->numQuestion);
			return false;
		}

		if (!in_array($this->bankCardOperation, [BankCardOperation::UPDATE_SUBSCRIBER, BankCardOperation::DELETE_SUBSCRIBER], true)) {
			if (empty($this->montant)) {
				$this->logger?->error('Invalid amount: ' . $this->montant);
				return false;
			}

			if (empty($this->reference) || strlen($this->reference) > 250) {
				$this->logger?->error('Invalid reference: must be 1-250 characters');
				return false;
			}
		}

		if (in_array($this->bankCardOperation, [BankCardOperation::UPDATE_SUBSCRIBER, BankCardOperation::DELETE_SUBSCRIBER], true)) {
			if (empty($this->subscriberRef)) {
				$this->logger?->error('Subscriber reference is required for this operation');
				return false;
			}
		}

		if (!empty($this->subscriberRef) && strlen($this->subscriberRef) > 250) {
			$this->logger?->error('Invalid subscriber reference: maximum 250 characters');
			return false;
		}

		$isCaptureAfterAuthorization = in_array($this->bankCardOperation, [BankCardOperation::DEBIT, BankCardOperation::CANCEL], true);

		if ($this->useForm) {
			// Using PayBox System (hosted payment form on PayBox platform)
			if (empty($this->porteurEmail) || strlen($this->porteurEmail) < 6 || strlen($this->porteurEmail) > 120 || !filter_var($this->porteurEmail, FILTER_VALIDATE_EMAIL)) {
				$this->logger?->error('Invalid customer email address');
				return false;
			}
		} else {
			// Using PayBox Direct (client-side payment form with HTTP request to PayBox platform)

			$isSubscriberManagementOnly = in_array($this->bankCardOperation, [BankCardOperation::DELETE_SUBSCRIBER], true);

			if (!$isSubscriberManagementOnly) {
				if (!empty($this->subscriberRef)) {
					// Using subscriber system (via card token)

					if (empty($this->porteur)) {
						$this->logger?->error('Card token is required for subscriber payment');
						return false;
					}
				} else {
					// Using standard system (manual card entry)

					if (!empty($this->porteur) && strlen($this->porteur) > 19) {
						$this->logger?->error('Invalid card number: maximum 19 digits');
						return false;
					}

					if (!empty($this->cvv) && (strlen($this->cvv) < 3 || strlen($this->cvv) > 4)) {
						$this->logger?->error('Invalid CVV code: must be 3-4 digits');
						return false;
					}
				}

				if (empty($this->getExpirationDateFormatted())) {
					$this->logger?->error('Invalid or missing card expiration date');
					return false;
				}
			}
		}

		if (!$this->useForm && $isCaptureAfterAuthorization) {
			// if (empty($this->numAppel) || strlen($this->numAppel) != 10) {
			if (empty($this->numAppel)) {
				$this->logger?->error('Call number is required for capture operation');
				return false;
			}

			// if (empty($this->numTransaction) || strlen($this->numTransaction) != 10) {
			if (empty($this->numTransaction)) {
				$this->logger?->error('Transaction number is required for capture operation');
				return false;
			}

			$this->porteur = null;
		}

		// ---------- Form method (PayBox System) ----------
		if ($this->useForm) {
			return $this->getHtml();
		}

		// ---------- HTTP method (PayBox Direct) ----------
		if (null === ($payboxResponse = $this->doHttpRequestToPayBox())) {
			return false;
		}
		return $payboxResponse;
	}

	/**
	 * Execute HTTP request to PayBox Direct API
	 * Builds POST data with all transaction parameters, makes HTTP request to PayBox,
	 * parses the response, validates the response code, and creates a PayBoxResponse object
	 * Logs all steps for debugging and compliance
	 * @return PayBoxResponse|null The response with transaction details, or null if request failed or was rejected
	 */
	private function doHttpRequestToPayBox(): ?PayBoxResponse
	{
		$urlPaiement = ($this->isTest ? self::URL_PAIEMENT_TEST : self::URL_PAIEMENT);

		$postData = [
			'VERSION' => $this->version->value,
			'TYPE' => $this->getTypeOperationFormatted(),
			'SITE' => $this->siteNumber,
			'RANG' => $this->rang,
			'CLE' => $this->httpPassword,

			'NUMQUESTION' => $this->numQuestion,
			'MONTANT' => $this->getFormattedAmount(),
			'DEVISE' => $this->getCurrencyCode(),
			'REFERENCE' => $this->reference,
			'REFABONNE' => $this->subscriberRef,

			'PORTEUR' => $this->porteur,
			'DATEVAL' => $this->getExpirationDateFormatted(),
			'CVV' => $this->cvv,

			'DATEQ' => date('dmYHis', $this->getTimestamp()),

			'ACTIVITE' => $this->getActiviteCode(),
			'ARCHIVAGE' => $this->archivage,
			'DIFFERE' => $this->differe,
			'NUMAPPEL' => $this->numAppel,
			'NUMTRANS' => $this->numTransaction,
			'AUTORISATION' => $this->autorisation,
			'PAYS' => '', // empty field allowing card country code to be returned in response
		];

		foreach ($postData as $cleVar => $value) {
			$postData[$cleVar] = trim($value ?? '');
		}
		$queryString = http_build_query($postData);

		// Log
		$this->logger?->info('URL Paiement Paybox : ' . $urlPaiement);
		$this->logger?->info('QueryString envoyée : ' . $queryString);
		$this->logger?->info('Référence achat : ' . $postData['REFERENCE']);

		// Appel de l'URL Paybox avec les arguments POST
		$res = $this->requestExecutor->execute(HTTPMethod::POST, $urlPaiement, $postData);

		if (null === $res) {
			$this->logger?->error('Appel Paybox échoué');
			return null;
		}

		$this->logger?->info('Résultat appel Paybox : ' . $res);

		// Parsing return parameters
		parse_str($res, $tabArg);
		$responseCode = $tabArg['CODEREPONSE'] ?? '';

		$responseCodes = array_merge(self::$responseCodes, self::$visaResponseCodes);

		if ($responseCode !== '00000') {
			$this->logger?->error('Response code: ' . $responseCode.' ; Error message: ' . ($responseCodes[$responseCode] ?? 'Erreur inconnue'));
			return null;
		}

		$this->logger?->info('Response code: ' . $responseCode.' (Opération réussie)');

		$payBoxResponse = new PayBoxResponse();
		$payBoxResponse->setReference(!empty($tabArg['REFERENCE']) ? urldecode($tabArg['REFERENCE']) : null);
		$payBoxResponse->setResponseCode(!empty($responseCode) ? urldecode($responseCode) : null);
		$payBoxResponse->setAuthorisationNumber(!empty($tabArg['AUTORISATION']) ? urldecode($tabArg['AUTORISATION']) : null);
		$payBoxResponse->setCallNumber(!empty($tabArg['NUMAPPEL']) ? urldecode($tabArg['NUMAPPEL']) : null);
		$payBoxResponse->setTransactionNumber(!empty($tabArg['NUMTRANS']) ? urldecode($tabArg['NUMTRANS']) : null);
		$payBoxResponse->setCardNumber(!empty($tabArg['PORTEUR']) ? urldecode($tabArg['PORTEUR']) : null);
		$payBoxResponse->setCardHash(!empty($tabArg['REFABONNE']) ? urldecode($tabArg['REFABONNE']) : null);
		$payBoxResponse->setCardType(!empty($tabArg['TYPECARTE']) ? urldecode($tabArg['TYPECARTE']) : null);
		// unused vars: $tabArg['NUMQUESTION'] ; $tabArg['SHA-1']

		return $payBoxResponse;
	}

	/**
	 * Generate HTML form for PayBox System payment
	 * Builds complete HTML form with all PayBox parameters, HMAC signature, and hidden fields
	 * The form auto-submits to PayBox hosted payment page
	 * @return string Complete HTML form markup ready to display to customer
	 */
	private function getHtml(): string
	{
		// variables required by PayBox
		$pbxVars = [
			'PBX_SITE' => $this->siteNumber,
			'PBX_RANG' => $this->rang,
			'PBX_IDENTIFIANT' => $this->identifier,
			'PBX_LANGUE' => $this->getLanguageCode(),
			'PBX_TOTAL' => $this->getAmount(),
			'PBX_DEVISE' => $this->getCurrencyCode(),
			'PBX_CMD' => $this->reference,
			'PBX_PORTEUR' => $this->porteurEmail,
			'PBX_REFABONNE' => $this->subscriberRef,
			'PBX_RETOUR' => $this->getReturnedVars(),
			'PBX_HASH' => 'SHA512',
			'PBX_TIME' => date('c', $this->getTimestamp()),
			'PBX_AUTOSEULE' => (in_array($this->bankCardOperation, [BankCardOperation::AUTHORIZATION_ONLY, BankCardOperation::REGISTER_SUBSCRIBER], true) ? 'O' : 'N'),
			'PBX_REPONDRE_A' => $this->urlIpn,
			'PBX_RUF1' => 'POST',
			'PBX_EFFECTUE' => $this->urlResponseOk,
			'PBX_REFUSE' => $this->urlResponseRefused,
			'PBX_ANNULE' => $this->urlResponseCanceled,
			'PBX_ATTENTE' => $this->urlResponseWaiting,
			'PBX_TYPEPAIEMENT' => 'CARTE',
			'PBX_TYPECARTE' => 'CB',
			'PBX_DISPLAY' => $this->getFormTimeout(),
		];

		if ($this->is3DSecureV2()) {
			$pbxVars['PBX_SHOPPINGCART'] = $this->getShoppingCartAsXml();
			$pbxVars['PBX_BILLING'] = $this->getBillingAddressAsXml();
		}

		// Calculate HMAC signature
		$hmac = $this->getHmac($pbxVars);

		// Build HTML form
		$formAction = htmlspecialchars($this->isTest ? self::URL_FORM_TEST : self::URL_FORM, ENT_QUOTES, 'UTF-8');
		$formClass = htmlspecialchars($this->formCssClass ?? '', ENT_QUOTES, 'UTF-8');
		$form = '<form method="POST" action="' . $formAction . '" class="' . $formClass . '">';

		foreach ($pbxVars as $index => $value) {
			$fieldName = htmlspecialchars($index, ENT_QUOTES, 'UTF-8');
			$fieldValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
			$form .= '<input type="hidden" name="' . $fieldName . '" value="' . $fieldValue . '">';
		}

		$buttonClass = htmlspecialchars($this->buttonCssClass ?? 'btn btn-primary', ENT_QUOTES, 'UTF-8');
		$buttonText = htmlspecialchars($this->buttonText ?? 'Pay', ENT_QUOTES, 'UTF-8');
		$hmacValue = htmlspecialchars($hmac, ENT_QUOTES, 'UTF-8');

		$form .= '<input type="hidden" name="PBX_HMAC" value="' . $hmacValue . '">'
			. '<input type="submit" class="' . $buttonClass . '" value="' . $buttonText . '">'
			. '</form>';

		return $form;
	}

	/**
	 * Get PayBox operation type code based on transaction type and subscriber status
	 * Maps internal BankCardOperation enum to PayBox TYPE parameter codes
	 * Uses different codes for subscriber operations (00051-00055) vs standard operations (00001-00005)
	 * @return string The PayBox operation code (e.g., "00003" for authorization+debit, "00053" for subscriber auth+debit)
	 */
	private function getTypeOperationFormatted(): string
	{
		if (!empty($this->subscriberRef)) {
			if ($this->bankCardOperation === BankCardOperation::AUTHORIZATION_ONLY) {
				return '00051';
			}
			if ($this->bankCardOperation === BankCardOperation::DEBIT) {
				return '00052';
			}
			if ($this->bankCardOperation === BankCardOperation::AUTHORIZATION_AND_DEBIT) {
				return '00053';
			}
			if ($this->bankCardOperation === BankCardOperation::CREDIT) {
				return '00054';
			}
			if ($this->bankCardOperation === BankCardOperation::CANCEL) {
				return '00055';
			}
		}

		return match($this->bankCardOperation) {
			BankCardOperation::AUTHORIZATION_ONLY => '00001',
			BankCardOperation::DEBIT => '00002',
			BankCardOperation::AUTHORIZATION_AND_DEBIT => '00003',
			BankCardOperation::CREDIT => '00004',
			BankCardOperation::CANCEL => '00005',
			BankCardOperation::REFUND => '00014',
			BankCardOperation::REGISTER_SUBSCRIBER => '00056',
			BankCardOperation::UPDATE_SUBSCRIBER => '00057',
			BankCardOperation::DELETE_SUBSCRIBER => '00058',
		};
	}

	/**
	 * Generate PBX_RETOUR parameter for PayBox System form
	 * Defines which variables PayBox should return after payment processing
	 * Maps PayBox internal codes to custom parameter names (e.g., "amount:M" means return amount as M parameter)
	 * Max length is 150 characters to avoid PayBox error
	 * @return string Semicolon-separated list of return variable mappings
	 */
	private function getReturnedVars(): string
	{
		// Total length of $returnedVars must not exceed 150 characters (otherwise PayBox error)
		//$returnedVars = 'amount:M;reference:R;authorization_number:A;call_number:T;transaction_number:S;card_last_digits:J;card_expiry_date:D;response_code:E;3d_secure_authentication:F;3d_secure_enabled:G;3d_secure_version:v';
		$returnedVars = 'amount:M;ref:R;authorizt_nb:A;call_nb:T;transact_nb:S;bc_type:C;bc_ldigit:J;bc_expdate:D;response_code:E;3ds:G;3ds_auth:F;3ds_v:v';
		if ($this->bankCardOperation === BankCardOperation::REGISTER_SUBSCRIBER) {
			$returnedVars .= ';card_ref:U;bin6:N';
		}

		return $returnedVars;
	}

	/**
	 * Get transaction timestamp
	 * Returns custom date if set, otherwise current time
	 * @return int Unix timestamp
	 */
	private function getTimestamp(): int
	{
		return ($this->date !== null ? $this->date->getTimestamp() : time());
	}

	/**
	 * Validate HTTP password format for PayBox Direct
	 * Checks that password is 8-10 characters long as required by PayBox
	 * @return bool True if valid, false otherwise
	 */
	private function _checkHttpPassword(): bool
	{
		return !empty($this->httpPassword) && strlen($this->httpPassword) >= 8 && strlen($this->httpPassword) <= 10;
	}

	/**
	 * Validate identifier format for PayBox System
	 * Checks that identifier is 1-9 digits long as required by PayBox
	 * @return bool True if valid, false otherwise
	 */
	private function _checkIdentifier(): bool
	{
		return !empty($this->identifier) && strlen($this->identifier) >= 1 && strlen($this->identifier) <= 9;
	}

	/**
	 * Validate secret key format for PayBox System HMAC
	 * Checks that secret key is exactly 128 characters (hexadecimal representation)
	 * @return bool True if valid, false otherwise
	 */
	private function _checkSecretKey(): bool
	{
		return !empty($this->secretKey) && strlen($this->secretKey) === 128;
	}

	/**
	 * Generate HMAC-SHA512 signature for PayBox System form
	 * Creates cryptographic signature to secure form data transmission to PayBox
	 * Uses secret key to generate hash of all form parameters
	 * @param array<string, mixed> $varsList Associative array of PayBox parameters
	 * @return string Uppercase hexadecimal HMAC signature
	 */
	private function getHmac(array $varsList): string
	{
		$vars = [];

		foreach ($varsList as $key => $value) {
			$vars[] = $key . '=' . $value;
		}

		$msg = implode('&', $vars);
		$binKey = pack('H*', $this->secretKey);
		
		return strtoupper(hash_hmac('sha512', $msg, $binKey));
	}

	/**
	 * Convert locale to PayBox language code
	 * Maps locale strings to ISO 3166-1 alpha-3 country codes for PayBox PBX_LANGUE parameter
	 * Defaults to FRA (French) if locale not recognized
	 * @return string Three-letter country code (e.g., "GBR", "ESP", "FRA")
	 */
	private function getLanguageCode(): string
	{
		if (in_array($this->locale, ['en', 'en_GB', 'en-GB', 'GB', 'UK'], true)) {
			return 'GBR'; // English
		}
		if (in_array($this->locale, ['es', 'es_ES', 'es-ES', 'ES'], true)) {
			return 'ESP'; // Espagnol
		}
		if (in_array($this->locale, ['pt', 'pt_PT', 'pt-PT', 'PT'], true)) {
			return 'PRT'; // Portugais
		}
		if (in_array($this->locale, ['it', 'it_IT', 'it-IT', 'IT'], true)) {
			return 'ITA'; // Italien
		}
		if (in_array($this->locale, ['de', 'de_DE', 'de-DE', 'DE'], true)) {
			return 'DEU'; // Allemand
		}
		if (in_array($this->locale, ['nl', 'nl_NL', 'nl-NL', 'NL'], true)) {
			return 'NLD'; // Néerlandais
		}
		if (in_array($this->locale, ['sv_SE', 'sv-SE', 'SE'], true)) {
			return 'SWE'; // Suédois
		}
		return 'FRA';
	}

	/**
	 * Convert call origin enum to PayBox activity code
	 * Maps BankCardCallOrigin enum to PayBox ACTIVITE parameter codes
	 * Informs the bank how the transaction was initiated (internet, phone, mail, etc.)
	 * @return string|null Three-digit activity code (e.g., "024" for internet), or null if not set
	 */
	private function getActiviteCode(): ?string
	{
		if (null === $this->activite) {
			return null;
		}

		return match ($this->activite) {
			BankCardCallOrigin::NOT_SPECIFIED => '020',
			BankCardCallOrigin::TELEPHONE_ORDER => '021',
			BankCardCallOrigin::MAIL_ORDER => '022',
			BankCardCallOrigin::MINITEL => '023',
			BankCardCallOrigin::INTERNET_PAYMENT => '024',
			BankCardCallOrigin::RECURRING_PAYMENT => '027',
		};
	}

	/**
	 * Convert amount to minor currency units (cents)
	 * Multiplies by 100 and rounds to convert euros/dollars to cents
	 * Example: 10.50 EUR becomes 1050
	 * @return int Amount in smallest currency unit
	 */
	private function getAmount(): int
	{
		return round($this->montant * 100, 2);
	}

	/**
	 * Format amount for PayBox MONTANT parameter
	 * Converts amount to string and pads with leading zeros to 10 digits
	 * Example: 1050 becomes "0000001050"
	 * @return string|null Zero-padded 10-digit amount string
	 */
	private function getFormattedAmount(): ?string
	{
		$montantFormate = (string) $this->getAmount();
		return str_pad($montantFormate, 10, '0', STR_PAD_LEFT);
	}

	/**
	 * Convert currency code to ISO 4217 numeric code
	 * Maps three-letter currency codes to PayBox DEVISE parameter numeric codes
	 * Example: "EUR" becomes 978, "USD" becomes 840
	 * @return int|null Three-digit numeric currency code
	 */
	private function getCurrencyCode(): ?int
	{
		return Currency::getNumericCode($this->devise);
	}

	/**
	 * Format card expiration date for PayBox DATEVAL parameter
	 * Formats as MMYY (e.g., "1225" for December 2025)
	 * @return string|null Four-digit expiration date, or null if not set
	 */
	private function getExpirationDateFormatted(): ?string
	{
		if (null !== $this->expirationDate) {
			return $this->expirationDate->format('my');
		}
		/*if (strlen($this->dateValidite) === 10) { // format yyyy-mm-dd
			return substr($this->dateValidite, 5, 2) . substr($this->dateValidite, 2, 2);
		}
		if (strlen($this->dateValidite) === 7) { // format mm/yyyy
			return substr($this->dateValidite, 0, 2) . substr($this->dateValidite, -2);
		}
		if (strlen($this->dateValidite) === 4) { // format mmyy
			return substr($this->dateValidite, 0, 2) . substr($this->dateValidite, -2);
		}*/
		return null;
	}

}
