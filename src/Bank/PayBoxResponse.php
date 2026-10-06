<?php

namespace Osimatic\Bank;

/**
 * Class representing a PayBox payment gateway response
 * Implements BankCardOperationResponseInterface to provide standardized access to payment response data
 * Handles both standard payments and 3D Secure authentication responses
 */
class PayBoxResponse implements BankCardOperationResponseInterface
{
	/** 3D Secure protocol version 1 */
	public const int _3D_SECURE_VERSION_1 = 1;

	/** 3D Secure protocol version 2 */
	public const int _3D_SECURE_VERSION_2 = 2;

	/**
	 * Merchant's order/transaction reference
	 * The reference ID provided during payment request, returned by PayBox to identify the transaction
	 * @var string|null
	 */
	private ?string $reference = null;

	/**
	 * PayBox response code indicating the transaction result
	 * Code '00000' indicates success, other codes indicate various error conditions
	 * Maps to CODEREPONSE in PayBox API
	 * Null when the code is missing or not a known PayBox response code
	 * @var PayBoxResponseCode|null
	 */
	private ?PayBoxResponseCode $responseCode = null;

	/**
	 * Call number (NUMAPPEL) returned by PayBox
	 * 10-digit reference number from PayBox/Verifone, required for subsequent operations like capture
	 * @var string|null
	 */
	private ?string $callNumber = null;

	/**
	 * Transaction number (NUMTRANS) returned by PayBox
	 * 10-digit unique transaction identifier from PayBox/Verifone
	 * @var string|null
	 */
	private ?string $transactionNumber = null;

	/**
	 * Authorization number from the acquiring bank
	 * The authorization code provided by the bank approving the transaction
	 * @var string|null
	 */
	private ?string $authorizationNumber = null;

	/**
	 * Card type/brand
	 * The card network (e.g., VISA, MASTERCARD, CB, AMEX)
	 * @var string|null
	 */
	private ?string $cardType = null;

	/**
	 * Masked card number
	 * Card number with middle digits masked (e.g., "1234********5678")
	 * Constructed from BIN6 (first 6 digits) and last 4 digits
	 * @var string|null
	 */
	private ?string $cardNumber = null;

	/**
	 * Last 4 digits of the card number
	 * Used for card identification without exposing the full number
	 * @var string|null
	 */
	private ?string $cardLastDigits = null;

	/**
	 * Card expiration date
	 * The month and year when the card expires
	 * @var \DateTime|null
	 */
	private ?\DateTime $cardExpirationDateTime = null;

	/**
	 * Card hash/token for recurring payments
	 * Subscriber reference token that can be used for future transactions without card details
	 * Extracted from the card_ref parameter returned by PayBox
	 * @var string|null
	 */
	private ?string $cardHash = null;

	/**
	 * 3D Secure enablement status
	 * Indicates whether 3D Secure authentication was used for this transaction
	 * @var bool
	 */
	private bool $_3DSecureEnabled = false;

	/**
	 * 3D Secure authentication status
	 * Possible values:
	 * - "Y": Cardholder authenticated
	 * - "A": Authentication forced by the buyer's bank
	 * - "U": Cardholder authentication could not be performed
	 * - "N": Cardholder not authenticated
	 * @var string|null
	 */
	private ?string $_3DSecureAuthentication = null;

	/**
	 * 3D Secure protocol version used
	 * Either _3D_SECURE_VERSION_1 (1) or _3D_SECURE_VERSION_2 (2)
	 * Version 2 provides enhanced security and additional authentication data
	 * @var int|null
	 */
	private ?int $_3DSecureVersion = null;

	/**
	 * Create a PayBoxResponse from Symfony HTTP request
	 * Extracts PayBox response parameters from the HTTP request
	 * @param \Symfony\Component\HttpFoundation\Request $request The HTTP request containing PayBox response
	 * @return PayBoxResponse The parsed PayBox response
	 */
	public static function getFromHttpRequest(\Symfony\Component\HttpFoundation\Request $request): PayBoxResponse
	{
		return self::getFromRequest([
			'ref' => \Osimatic\Data\Input::get($request, 'ref'),
			'response_code' => \Osimatic\Data\Input::get($request, 'response_code'),
			'call_nb' => \Osimatic\Data\Input::get($request, 'call_nb'),
			'transact_nb' => \Osimatic\Data\Input::get($request, 'transact_nb'),
			'authorizt_nb' => \Osimatic\Data\Input::get($request, 'authorizt_nb'),
			'card_ref' => \Osimatic\Data\Input::get($request, 'card_ref'),
			'bc_type' => \Osimatic\Data\Input::get($request, 'bc_type'),
			'bc_ldigit' => \Osimatic\Data\Input::get($request, 'bc_ldigit'),
			'bin6' => \Osimatic\Data\Input::get($request, 'bin6'),
			'bc_expdate' => \Osimatic\Data\Input::get($request, 'bc_expdate'),
			'3ds' => \Osimatic\Data\Input::get($request, '3ds'),
			'3ds_auth' => \Osimatic\Data\Input::get($request, '3ds_auth'),
			'3ds_v' => \Osimatic\Data\Input::get($request, '3ds_v'),
		]);
	}

	/**
	 * Create a PayBoxResponse from request parameters array
	 * Parses PayBox response parameters and populates the response object
	 * @param array $request Array of PayBox response parameters
	 * @return PayBoxResponse The populated PayBox response object
	 */
	public static function getFromRequest(array $request): PayBoxResponse
	{
		$payBoxResponse = new PayBoxResponse();
		$payBoxResponse->setReference(!empty($request['ref']) ? urldecode($request['ref']) : null);
		$payBoxResponse->setResponseCode(!empty($request['response_code']) ? PayBoxResponseCode::parse(urldecode($request['response_code'])) : null);
		$payBoxResponse->setCallNumber(!empty($request['call_nb']) ? urldecode($request['call_nb']) : null);
		$payBoxResponse->setTransactionNumber(!empty($request['transact_nb']) ? urldecode($request['transact_nb']) : null);
		$payBoxResponse->setAuthorisationNumber(!empty($request['authorizt_nb']) ? urldecode($request['authorizt_nb']) : null);

		if (!empty($request['card_ref'])) {
			// card_ref contains a string like: "abc123abc12  2206  ---" (the first part is the card token, used for future debits)
			$payBoxResponse->setCardHash(explode('  ', $request['card_ref'])[0] ?? null);
		}

		$payBoxResponse->setCardType(!empty($request['bc_type']) ? urldecode($request['bc_type']) : null);
		$payBoxResponse->setCardLastDigits(!empty($request['bc_ldigit']) ? urldecode($request['bc_ldigit']) : null);
		if (!empty($request['bin6'])) {
			$payBoxResponse->setCardNumber($request['bin6'].'********'.($request['bc_ldigit'] ?? '**'));
		}

		if (!empty($cardExpirationDate = urldecode($request['bc_expdate'] ?? ''))) {
			$payBoxResponse->setCardExpirationDateTime(BankCard::getExpirationDateFromYearAndMonth((int) ('20'.substr($cardExpirationDate, 0, 2)), (int) (substr($cardExpirationDate, 2, 2))));
		}

		$payBoxResponse->set3DSecureEnabled(($request['3ds'] ?? null) === 'O');
		if ($payBoxResponse->is3DSecureEnabled()) {
			$payBoxResponse->set3DSecureAuthentication($request['3ds_auth'] ?? null);
			$payBoxResponse->set3DSecureVersion(!empty($request['3ds_v'])?self::_3D_SECURE_VERSION_2:self::_3D_SECURE_VERSION_1);
		}

		return $payBoxResponse;
	}

	/**
	 * Check if the payment operation was successful
	 * @return bool True if response code is '00000' (success), false otherwise
	 */
	public function isSuccess(): bool
	{
		return $this->responseCode?->isSuccess() ?? false;
	}

	/**
	 * Get the order reference (implements BankCardOperationResponseInterface)
	 * @return string|null The merchant's order reference
	 */
	public function getOrderReference(): ?string
	{
		return $this->reference;
	}

	/**
	 * Get the card reference token (implements BankCardOperationResponseInterface)
	 * @return string|null The card hash/token for future operations
	 */
	public function getCardReference(): ?string
	{
		return $this->cardHash;
	}


	/**
	 * Get the order/transaction reference
	 * @return string|null The reference ID
	 */
	public function getReference(): ?string
	{
		return $this->reference;
	}

	/**
	 * Set the order/transaction reference
	 * @param string|null $reference The reference ID to set
	 */
	public function setReference(?string $reference): void
	{
		$this->reference = $reference;
	}

	/**
	 * Get the PayBox response code
	 * @return PayBoxResponseCode|null The response code (SUCCESS = '00000'), or null if missing or unknown
	 */
	public function getResponseCode(): ?PayBoxResponseCode
	{
		return $this->responseCode;
	}

	/**
	 * Set the PayBox response code
	 * @param PayBoxResponseCode|null $responseCode The response code to set
	 */
	public function setResponseCode(?PayBoxResponseCode $responseCode): void
	{
		$this->responseCode = $responseCode;
	}

	/**
	 * Get the call number
	 * @return string|null The unique identifier for this API call
	 */
	public function getCallNumber(): ?string
	{
		return $this->callNumber ?? null;
	}

	/**
	 * Set the call number
	 * @param string|null $callNumber The unique identifier for this API call
	 */
	public function setCallNumber(?string $callNumber): void
	{
		$this->callNumber = $callNumber;
	}

	/**
	 * Get the transaction number
	 * @return string|null The unique transaction identifier
	 */
	public function getTransactionNumber(): ?string
	{
		return $this->transactionNumber ?? null;
	}

	/**
	 * Set the transaction number
	 * @param string|null $transactionNumber The unique transaction identifier
	 */
	public function setTransactionNumber(?string $transactionNumber): void
	{
		$this->transactionNumber = $transactionNumber;
	}

	/**
	 * Get the authorization number
	 * @return string|null The authorization code from the bank
	 */
	public function getAuthorisationNumber(): ?string
	{
		return $this->authorizationNumber ?? null;
	}

	/**
	 * Set the authorization number
	 * @param string|null $authorizationNumber The authorization code from the bank
	 */
	public function setAuthorisationNumber(?string $authorizationNumber): void
	{
		$this->authorizationNumber = $authorizationNumber;
	}

	/**
	 * Get the card type
	 * @return string|null The card brand/type
	 */
	public function getCardType(): ?string
	{
		return $this->cardType;
	}

	/**
	 * Set the card type
	 * @param string|null $cardType The card brand/type
	 */
	public function setCardType(?string $cardType): void
	{
		$this->cardType = $cardType;
	}

	/**
	 * Get the masked card number
	 * @return string|null The card number with middle digits masked
	 */
	public function getCardNumber(): ?string
	{
		return $this->cardNumber;
	}

	/**
	 * Set the masked card number
	 * @param string|null $cardNumber The card number with middle digits masked
	 */
	public function setCardNumber(?string $cardNumber): void
	{
		$this->cardNumber = $cardNumber;
	}

	/**
	 * Get the last digits of the card number
	 * @return string|null The last 4 digits of the card
	 */
	public function getCardLastDigits(): ?string
	{
		return $this->cardLastDigits ?? null;
	}

	/**
	 * Set the last digits of the card number
	 * @param string|null $cardLastDigits The last 4 digits of the card
	 */
	public function setCardLastDigits(?string $cardLastDigits): void
	{
		$this->cardLastDigits = $cardLastDigits;
	}

	/**
	 * Get the card expiration date
	 * @return \DateTime|null The expiration date of the card
	 */
	public function getCardExpirationDateTime(): ?\DateTime
	{
		return $this->cardExpirationDateTime;
	}

	/**
	 * Set the card expiration date
	 * @param \DateTime|null $cardExpirationDateTime The expiration date of the card
	 */
	public function setCardExpirationDateTime(?\DateTime $cardExpirationDateTime): void
	{
		$this->cardExpirationDateTime = $cardExpirationDateTime;
	}

	/**
	 * Get the card hash/token
	 * @return string|null The card token for future transactions
	 */
	public function getCardHash(): ?string
	{
		return $this->cardHash ?? null;
	}

	/**
	 * Set the card hash/token
	 * @param string|null $cardHash The card token for future transactions
	 */
	public function setCardHash(?string $cardHash): void
	{
		$this->cardHash = $cardHash;
	}

	/**
	 * Check if 3D Secure was enabled for this transaction
	 * @return bool True if 3D Secure was used, false otherwise
	 */
	public function is3DSecureEnabled(): bool
	{
		return $this->_3DSecureEnabled;
	}

	/**
	 * Set whether 3D Secure was enabled for this transaction
	 * @param bool $_3DSecureEnabled True if 3D Secure was used
	 */
	public function set3DSecureEnabled(bool $_3DSecureEnabled): void
	{
		$this->_3DSecureEnabled = $_3DSecureEnabled;
	}

	/**
	 * Get the 3D Secure authentication status
	 * @return string|null The authentication status code (Y/A/U/N)
	 */
	public function get3DSecureAuthentication(): ?string
	{
		return $this->_3DSecureAuthentication;
	}

	/**
	 * Set the 3D Secure authentication status
	 * @param string|null $_3DSecureAuthentication The authentication status code (Y/A/U/N)
	 */
	public function set3DSecureAuthentication(?string $_3DSecureAuthentication): void
	{
		$this->_3DSecureAuthentication = $_3DSecureAuthentication;
	}

	/**
	 * Get the 3D Secure protocol version
	 * @return int|null The 3D Secure version (1 or 2)
	 */
	public function get3DSecureVersion(): ?int
	{
		return $this->_3DSecureVersion;
	}

	/**
	 * Set the 3D Secure protocol version
	 * @param int|null $_3DSecureVersion The 3D Secure version (1 or 2)
	 */
	public function set3DSecureVersion(?int $_3DSecureVersion): void
	{
		$this->_3DSecureVersion = $_3DSecureVersion;
	}


	// ========== DEPRECATED METHODS ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * @deprecated
	 */
	public function getAuthorizationNumber(): ?string
	{
		return $this->authorizationNumber ?? null;
	}

	/**
	 * @deprecated
	 */
	public function setAuthorizationNumber(?string $authorizationNumber): void
	{
		$this->authorizationNumber = $authorizationNumber;
	}

}