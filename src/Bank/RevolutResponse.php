<?php 

namespace Osimatic\Bank;

/**
 * Class representing a Revolut payment gateway response
 * Implements BankCardOperationResponseInterface to provide standardized access to payment response data
 * Handles responses from the Revolut Merchant API for order creation and payment processing
 * API Reference: https://developer.revolut.com/docs/api-reference/merchant/#tag/Orders/operation/createOrder
 */
class RevolutResponse implements BankCardOperationResponseInterface
{
    /**
     * Unique order identifier from Revolut
     * @var string|null
     */
    private ?string $id = null;

    /**
     * Public order identifier for customer-facing URLs
     * @var string|null
     */
    private ?string $publicId = null;

    /**
     * Order type (e.g., "PAYMENT")
     * @var string|null
     */
    private ?string $type = null;

    /**
     * Current order state/status (e.g., "PENDING", "PROCESSING", "COMPLETED", "CANCELLED")
     * @var string|null
     */
    private ?string $state = null;

    /**
     * Order creation timestamp
     * @var \DateTime|null
     */
    private ?\DateTime $creationDate = null;

    /**
     * Last update timestamp
     * @var \DateTime|null
     */
    private ?\DateTime $updateDate = null;

    /**
     * Payment capture mode: "AUTOMATIC" (immediate capture) or "MANUAL" (delayed capture)
     * @var string|null
     */
    private ?string $captureMode = null;

    /**
     * Merchant's external order reference/ID
     * @var string|null
     */
    private ?string $merchantOrderExtRef = null;

    /**
     * Order amount in minor currency units (cents)
     * Example: 1000 = 10.00
     * @var int|null
     */
    private ?int $amount = null;

    /**
     * Currency code (ISO 4217)
     * @var string|null
     */
    private ?string $currency = null;

    /**
     * Revolut checkout page URL for customer payment
     * @var string|null
     */
    private ?string $checkoutUrl = null;

	/**
	 * Error identifier if the request failed
	 * @var string|null
	 */
	private ?string $errorId = null;

    /**
     * Last 4 digits of the card number
     * @var string|null
     */
    private ?string $cardLastDigits = null;

    /**
     * Card expiration date
     * @var \DateTime|null
     */
    private ?\DateTime $cardExpiration = null;


	/**
	 * Get the authorization number (implements BankCardOperationResponseInterface)
	 * Note: Revolut API does not provide this field, returns null
	 * @return string|null Always returns null for Revolut
	 */
	public function getAuthorisationNumber(): ?string
	{
		return null;
	}

	/**
	 * Get the merchant's order reference (implements BankCardOperationResponseInterface)
	 * @return string|null The external order reference
	 */
	public function getOrderReference(): ?string
	{
		return $this->merchantOrderExtRef;
	}

	/**
	 * Get the card reference token (implements BankCardOperationResponseInterface)
	 * Note: Revolut API does not provide this field, returns null
	 * @return string|null Always returns null for Revolut
	 */
	public function getCardReference(): ?string
	{
		return null;
	}

	/**
	 * Get the call number (implements BankCardOperationResponseInterface)
	 * Note: Revolut API does not provide this field, returns null
	 * @return string|null Always returns null for Revolut
	 */
	public function getCallNumber(): ?string
	{
		return null;
	}



    /**
     * Get the unique Revolut order identifier
     * @return string|null The Revolut order ID
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set the unique Revolut order identifier
     * @param string|null $id The Revolut order ID to set
     */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the public order identifier for customer-facing URLs
     * @return string|null The public order ID
     */
    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    /**
     * Set the public order identifier
     * @param string|null $publicId The public order ID to set
     */
    public function setPublicId(?string $publicId): void
    {
        $this->publicId = $publicId;
    }

    /**
     * Get the order type
     * @return string|null The order type (typically "PAYMENT")
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set the order type
     * @param string|null $type The order type to set
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * Get the current order state/status
     * @return string|null The order state (e.g., "PENDING", "PROCESSING", "COMPLETED", "CANCELLED")
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * Set the order state/status
     * @param string|null $state The order state to set
     */
    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    /**
     * Get the order creation timestamp
     * @return \DateTime|null When the order was created
     */
    public function getCreationDate(): ?\DateTime
    {
        return $this->creationDate;
    }

    /**
     * Set the order creation timestamp
     * @param \DateTime|null $creationDate The creation timestamp to set
     */
    public function setCreationDate(?\DateTime $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    /**
     * Get the last update timestamp
     * @return \DateTime|null When the order was last updated
     */
    public function getUpdateDate(): ?\DateTime
    {
        return $this->updateDate;
    }

    /**
     * Set the last update timestamp
     * @param \DateTime|null $updateDate The update timestamp to set
     */
    public function setUpdateDate(?\DateTime $updateDate): void
    {
        $this->updateDate = $updateDate;
    }

    /**
     * Get the payment capture mode
     * @return string|null "AUTOMATIC" for immediate capture, "MANUAL" for delayed capture
     */
    public function getCaptureMode(): ?string
    {
        return $this->captureMode;
    }

    /**
     * Set the payment capture mode
     * @param string|null $captureMode The capture mode to set
     */
    public function setCaptureMode(?string $captureMode): void
    {
        $this->captureMode = $captureMode;
    }

    /**
     * Get the merchant's external order reference
     * @return string|null The external order reference/ID
     */
    public function getMerchantOrderExtRef(): ?string
    {
        return $this->merchantOrderExtRef;
    }

    /**
     * Set the merchant's external order reference
     * @param string|null $merchantOrderExtRef The external order reference to set
     */
    public function setMerchantOrderExtRef(?string $merchantOrderExtRef): void
    {
        $this->merchantOrderExtRef = $merchantOrderExtRef;
    }

    /**
     * Get the order amount in minor currency units (cents)
     * @return integer|null The amount (e.g., 1000 = 10.00)
     */
    public function getAmount(): ?int
    {
        return $this->amount;
    }

    /**
     * Set the order amount in minor currency units (cents)
     * @param integer|null $amount The amount to set (e.g., 1000 = 10.00)
     */
    public function setAmount(?int $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * Get the currency code
     * @return string|null The ISO 4217 currency code
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * Set the currency code
     * @param string|null $currency The ISO 4217 currency code to set
     */
    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * Get the Revolut checkout page URL
     * @return string|null The checkout URL for customer payment
     */
    public function getCheckoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    /**
     * Set the Revolut checkout page URL
     * @param string|null $checkoutUrl The checkout URL to set
     */
    public function setCheckoutUrl(?string $checkoutUrl): void
    {
        $this->checkoutUrl = $checkoutUrl;
    }

	/**
	 * Get the error identifier
	 * @return string|null The error ID if the request failed, null if successful
	 */
	public function getErrorId(): ?string
	{
		return $this->errorId;
	}

	/**
	 * Set the error identifier
	 * @param string|null $errorId The error ID to set
	 */
	public function setErrorId(?string $errorId): void
	{
		$this->errorId = $errorId;
	}

    /**
     * Get the last 4 digits of the card number
     * @return string|null The card's last 4 digits
     */
    public function getCardLastDigits(): ?string
    {
        return $this->cardLastDigits;
    }

    /**
     * Set the last 4 digits of the card number
     * @param string|null $cardLastDigits The card's last 4 digits to set
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
        return $this->cardExpiration;
    }

    /**
     * Set the card expiration date
     * @param \DateTime|null $cardExpiration The expiration date to set
     */
    public function setCardExpirationDateTime(?\DateTime $cardExpiration): void
    {
        $this->cardExpiration = $cardExpiration;
    }

	/**
	 * Check if the payment operation was successful
	 * @return bool True if no error occurred (errorId is null), false otherwise
	 */
	public function isSuccess(): bool
	{
		return $this->getErrorId() === null;
	}

    /**
	 * Get the transaction number (implements BankCardOperationResponseInterface)
	 * Returns the Revolut order ID as the transaction identifier
	 * @return string|null The Revolut order ID
	 */
	public function getTransactionNumber(): ?string
	{
		return $this->id ?? null;
	}

    /**
     * Create a RevolutResponse from Revolut API response data
     * Parses the response array from Revolut Merchant API and populates the response object
     * Extracts order details, payment information, and card data if available
     * @param array $request Array of Revolut API response parameters
     * @return RevolutResponse The populated Revolut response object
     */
    public static function getFromRequest(array $request): RevolutResponse
    {
        $orderAmount = $request['order_amount'] ?? null;
        $cardData = $request['payments'][0]['payment_method']['card'] ?? null;

        $revolutResponse = new RevolutResponse();
        $revolutResponse->setErrorId(!empty($request['errorId']) ? urldecode($request['errorId']) : null);
        $revolutResponse->setId(!empty($request['id']) ? urldecode($request['id']) : null);
        $revolutResponse->setPublicId(!empty($request['public_id']) ? urldecode($request['public_id']) : null);
        $revolutResponse->setType(!empty($request['type']) ? urldecode($request['type']) : null);
        $revolutResponse->setState(!empty($request['state']) ? urldecode($request['state']) : null);
		try {
			$revolutResponse->setCreationDate(!empty($request['created_at']) ? new \DateTime($request['created_at']) : null);
			$revolutResponse->setUpdateDate(!empty($request['updated_at']) ? new \DateTime($request['updated_at']) : null);
		} catch (\Exception) {}
        $revolutResponse->setCaptureMode(!empty($request['capture_mode']) ? urldecode($request['capture_mode']) : null);
        $revolutResponse->setMerchantOrderExtRef(!empty($request['merchant_order_ext_ref']) ? urldecode($request['merchant_order_ext_ref']) : null);
        $revolutResponse->setAmount(!empty($orderAmount) ? urldecode($orderAmount['value']) : null);
        $revolutResponse->setCurrency(!empty($orderAmount) ? urldecode($orderAmount['currency']) : null);
        $revolutResponse->setCheckoutUrl(!empty($request['checkout_url']) ? urldecode($request['checkout_url']) : null);
        $revolutResponse->setCardLastDigits(!empty($cardData['card_last_four']) ? urldecode($cardData['card_last_four']) : null);
        $revolutResponse->setCardExpirationDateTime(!empty($cardData['card_expiry']) ? BankCard::getExpirationDateFromString(urldecode($cardData['card_expiry'])) : null);
        
        return $revolutResponse;
    }



	// ========== DEPRECATED METHODS ==========
	// Backward compatibility. Will be removed in a future major version. Please update your code to use the new method names.

	/**
	 * @deprecated
	 */
	public function getCardExpiration(): ?\DateTime
	{
		return $this->cardExpiration;
	}

	/**
	 * @deprecated
	 */
	public function setCardExpiration(?\DateTime $cardExpiration): void
	{
		$this->cardExpiration = $cardExpiration;
	}

}